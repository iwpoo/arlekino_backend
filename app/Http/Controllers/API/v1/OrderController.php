<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\BankCard;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use chillerlan\QRCode\QROptions;
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use chillerlan\QRCode\QRCode;
use InvalidArgumentException;
use Throwable;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $orders = Order::where('user_id', auth()->id())
            ->with(['user', 'items', 'items.product'])
            ->paginate(10);

        // Добавляем данные QR-кода для нужных статусов
        $orders->getCollection()->transform(static function ($order): Order {
            if (in_array($order->status, ['assembling', 'shipped'])) {
                $order->qr_data = [
                    'has_qr' => !is_null($order->qr_token) && now()->lt($order->expires_at),
                    'expires_at' => $order->expires_at,
                    'target_status' => $order->status === 'assembling' ? 'shipped' : 'completed'
                ];
            }
            return $order;
        });

        return response()->json($orders);
    }

    public function precalculate(Request $request): JsonResponse
    {
        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:cart_items,id,user_id,'.Auth::id()
        ]);

        $itemIds = Arr::flatten($request->item_ids);
        $itemIds = array_filter($itemIds, 'is_int');

        // Здесь должна быть логика расчета стоимости
        // В реальном проекте нужно получать актуальные цены из БД

        $itemsTotal = Auth::user()
            ->cartItems()
            ->whereIn('id', $itemIds)
            ->with('product')
            ->get()
            ->sum(function ($item) {
                return $item->quantity * $item->product->price;
            });

        return response()->json([
            'total' => [
                'items' => $itemsTotal,
                'delivery' => 299, // По умолчанию первая доставка
                'total' => $itemsTotal + 299
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('store', Order::class);

        $request->validate([
            'payment_method' => 'required|in:card,cash',
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:cart_items,id' . (Auth::check() ? ',user_id,'.Auth::id() : ''),
            'items.*.quantity' => 'required|integer|min:1',
            'card_id' => 'nullable|required_if:payment_method,card|exists:bank_cards,id,user_id,'.Auth::id(),
            'address_id' => 'required',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $cartItems = Auth::check()
                    ? Auth::user()->cartItems()->with('product')->whereIn('id', collect($request->items)->pluck('id'))->get()
                    : collect(); // Для гостей нужно добавить свою логику

                // Проверяем, что все товары найдены
                if ($cartItems->count() !== count($request->items)) {
                    throw new Exception('Some items not found in cart');
                }

                // Рассчитываем итоговую сумму
                $itemsTotal = $cartItems->sum(function ($item) {
                    return $item->quantity * $item->product->price;
                });

                // Получаем стоимость доставки
                $deliveryCost = 299;

                $productIds = collect($request->items)->pluck('id');
                $products = Product::findMany($productIds)->keyBy('id');

                $deliveryPoints = [];
                foreach ($request->items as $item) {
                    $product = $products[$item['id']] ?? null;
                    if ($product && $firstPoint = $product->first_delivery_point) {
                        $deliveryPoints[] = $this->formatDeliveryPoint($firstPoint);
                    }
                }

                $uniquePoints = array_unique($deliveryPoints);
                $shippingAddress = !empty($uniquePoints)
                    ? implode('; ', $uniquePoints)
                    : 'Адрес не указан';

                $order = Order::create([
                    'uuid' => Str::uuid(),
                    'user_id' => Auth::id(),
                    'total_amount' => $itemsTotal + $deliveryCost,
                    'payment_method' => $request->payment_method,
                    'card_id' => $request->card_id,
                    'status' => 'pending',
                    'shipping_address' => $shippingAddress,
                    'user_address_id' => (int) $request->address_id,
                ]);

                // Добавляем товары в заказ
                $orderItems = $cartItems->map(function ($item) use ($order) {
                    return [
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->product->price,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();

                OrderItem::insert($orderItems);

                if (Auth::check()) {
                    Auth::user()->cartItems()->whereIn('id', collect($request->items)->pluck('id'))->delete();
                }

                $paymentResult = $this->processPayment($order, $request->payment_method, $request->card_id ?? null);

                return response()->json([
                    'success' => true,
                    'order' => $order->load(['items']),
                    'payment_data' => $paymentResult,
                ]);
            });
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function formatDeliveryPoint(array $point): string
    {
        $parts = [];
        if (!empty($point['city'])) $parts[] = $point['city'];
        if (!empty($point['name'])) $parts[] = $point['name'];
        if (!empty($point['location'])) $parts[] = $point['location'];
        return implode(', ', $parts);
    }

    private function processPayment(Order $order, string $paymentMethod, ?int $cardId): array
    {
        switch ($paymentMethod) {
            case 'card':
                $card = BankCard::find($cardId);
                return [
                    'type' => 'card',
                    'amount' => $order->total_amount,
                    'card_last_four' => $card->last_four,
                    // Здесь должна быть интеграция с платежной системой
                    // Возвращаем данные для редиректа или подтверждения
                ];

            case 'cash':
                return [
                    'type' => 'cash',
                    'message' => 'Оплата при получении',
                ];

            default:
                throw new InvalidArgumentException('Unsupported payment method');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order): JsonResponse
    {
        abort_if($order->user_id !== auth()->id(), 403);

        return response()->json($order->load('user', 'items.product.files'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,assembling,completed,canceled',
            'payment_method' => 'sometimes|string',
            'shipping_address' => 'sometimes|string'
        ]);

        $order->update($validated);
        return response()->json($order);
    }

    /**
     * Remove the specified resource from storage.
     */
//    public function destroy(Order $order): JsonResponse
//    {
//        abort_if($order->user_id !== auth()->id(), 403);
//
//        $order->delete();
//        return response()->json(null, 204);
//    }

    public function generateQR(Order $order): JsonResponse
    {
        abort_if($order->user_id !== auth()->id(), 403);

        $allowedStatuses = ['assembling', 'shipped'];
        if (!in_array($order->status, $allowedStatuses)) {
            return response()->json([
                'error' => 'QR-код можно сгенерировать только для заказов в статусах "assembling" или "shipped"'
            ], 400);
        }

        if (!$order->qr_token || now()->gt($order->expires_at)) {
            $token = Str::random(32);
            $order->update([
                'qr_token' => $token,
                'expires_at' => now()->addHours(24),
            ]);
        }

        $url = route('order.status.update', [
            'order' => $order->id,
        ]);

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_Q,
            'scale' => 10,
        ]);

        $qrcode = (new QRCode($options))->render($url);

        return response()->json([
            'qrcode' => $qrcode,
            'expires_at' => $order->expires_at,
        ]);
    }

    public function updateStatus(Order $order, Request $request): JsonResponse
    {
        Gate::authorize('updateStatus', $order);

        if (!Auth::check()) {
            Log::info('Unauthenticated user attempt');
            return response()->json(['error' => 'Требуется авторизация'], 401);
        }

        $user = Auth::user();
        $isCourier = $user->isCourier();
        $isSeller = $user->isSeller();

        $request->validate([
            'status' => 'required|string|in:pending,assembling,shipped,completed,canceled'
        ]);

        $newStatus = $request->input('status');

        $qrRequired = false;

        if ($order->status === 'assembling' && $newStatus === 'shipped') {
            $qrRequired = true;
        } elseif ($order->status === 'shipped' && $newStatus === 'completed') {
            $qrRequired = true;
        }

        if ($qrRequired) {
            $request->validate(['qr_token' => 'required|string']);

            $qrToken = $request->input('qr_token');

            // Проверка токена
            if (!$order->qr_token || $order->qr_token !== $qrToken) {
                Log::info('Invalid QR token', [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'provided_token' => $qrToken,
                    'expected_token' => $order->qr_token
                ]);
                return response()->json(['error' => 'Неверный QR-токен'], 401);
            }

            // Проверка срока действия
            if (now()->gt($order->qr_token_expiry)) {
                Log::info('Expired QR token', [
                    'user_id' => $user->id,
                    'order_id' => $order->id
                ]);
                return response()->json(['error' => 'Срок действия QR-кода истек'], 400);
            }
        }

        // Ограничение на частые запросы для курьеров
        if ($isCourier) {
            $key = 'courier_status_update_' . $user->id;
            RateLimiter::hit($key);

            if (RateLimiter::tooManyAttempts($key, 10)) {
                Log::info('Too many attempts by courier', ['courier_id' => $user->id]);
                return response()->json(['error' => 'Слишком много попыток'], 429);
            }
        }

        // Проверка прав и бизнес-логики
        if ($isCourier) {
            // Проверка назначения заказа курьеру
            if ($order->courier_id !== $user->id) {
                Log::info('Courier not assigned to order', [
                    'courier_id' => $user->id,
                    'order_courier_id' => $order->courier_id
                ]);
                return response()->json(['error' => 'Заказ не назначен этому курьеру'], 403);
            }

            // Курьер может менять статус только на shipped и completed
            $allowedStatuses = ['shipped', 'completed'];
            if (!in_array($newStatus, $allowedStatuses)) {
                Log::info('Invalid status change by courier', [
                    'courier_id' => $user->id,
                    'current_status' => $order->status,
                    'requested_status' => $newStatus
                ]);
                return response()->json([
                    'error' => 'Курьер может менять статус только на "shipped" или "completed"'
                ], 403);
            }

            // Проверка допустимости перехода статуса
            if ($order->status === 'assembling' && $newStatus !== 'shipped') {
                Log::info('Invalid status transition by courier', [
                    'courier_id' => $user->id,
                    'from_status' => $order->status,
                    'to_status' => $newStatus
                ]);
                return response()->json([
                    'error' => 'Недопустимый переход статуса: можно менять только на "shipped"'
                ], 403);
            }

            if ($order->status === 'shipped' && $newStatus !== 'completed') {
                Log::info('Invalid status transition by courier', [
                    'courier_id' => $user->id,
                    'from_status' => $order->status,
                    'to_status' => $newStatus
                ]);
                return response()->json([
                    'error' => 'Недопустимый переход статуса: можно менять только на "completed"'
                ], 403);
            }
        }

        // Проверки для продавца
        if ($isSeller) {
            $allowedStatuses = ['assembling', 'canceled'];
            if (!in_array($newStatus, $allowedStatuses)) {
                Log::info('Invalid status change by seller', [
                    'seller_id' => $user->id,
                    'current_status' => $order->status,
                    'requested_status' => $newStatus
                ]);
                return response()->json([
                    'error' => 'Продавец может менять статус только на "assembling" или "canceled"'
                ], 403);
            }
        }

        $order->status = $newStatus;

        // Инвалидация QR-токена при использовании
        if ($qrRequired) {
            $order->qr_token = null;
            $order->expires_at = null;
        }

        $order->save();

        Log::info('Order status updated', [
            'order_id' => $order->id,
            'from_status' => $order->getOriginal('status'),
            'to_status' => $newStatus,
            'user_id' => $user->id,
            'user_type' => $isCourier ? 'courier' : ($isSeller ? 'seller' : 'customer')
        ]);

        return response()->json($order);
    }
}
