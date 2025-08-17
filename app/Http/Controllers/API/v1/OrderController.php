<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\v1;

use App\Enums\DiscountType;
use App\Events\OrderCreate;
use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\BankCard;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRCode;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class OrderController extends Controller
{
    /** Статусы, с которыми можно работать QR */
    private const QR_ALLOWED_STATUSES = ['assembling', 'shipped'];

    /** Допустимые статусы переходов */
    private const ALLOWED_STATUSES_ALL = ['pending', 'assembling', 'shipped', 'completed', 'canceled'];

    /** Стоимость доставки (вынеси в config при желании) */
    private const DELIVERY_COST = 299;

    /**
     * Список заказов продавца — все заказы, где есть позиции с его товарами.
     * GET /api/v1/seller/orders  (вынесено в отдельный метод, чтобы не путать роли)
     */
    public function index(): JsonResponse
    {
        $sellerId = (int)auth()->id();

        $orders = Order::query()
            ->whereHas('items.product', static function ($q) use ($sellerId) {
                $q->where('user_id', $sellerId);
            })
            ->with([
                'user:id,name,email',
                'items' => static function ($q) use ($sellerId) {
                    $q->whereHas('product', static function ($q2) use ($sellerId) {
                        $q2->where('user_id', $sellerId);
                    })->with(['product']);
                },
            ])
            ->latest('id')
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * Предрасчёт корзины по списку cart_item.id
     */
    public function precalculate(Request $request): JsonResponse
    {
        $request->validate([
            'item_ids'   => 'required|array',
            'item_ids.*' => 'exists:cart_items,id,user_id,' . Auth::id(),
        ]);

        // Плоский массив ID и фильтрация на случай мусора
        $itemIds = array_values(array_filter(Arr::flatten($request->input('item_ids', [])), 'is_int'));

        $items = Auth::user()
            ->cartItems()
            ->whereIn('id', $itemIds)
            ->with('product')
            ->get();

        $itemsTotal = $items->sum(function ($cartItem) {
            return $cartItem->quantity * $this->discountedPrice($cartItem->product);
        });

        return response()->json([
            'total' => [
                'items'      => $itemsTotal,
                'delivery'   => self::DELIVERY_COST,
                'total'      => $itemsTotal + self::DELIVERY_COST,
            ],
        ]);
    }

    /**
     * Создание заказа
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('store', Order::class);

        $request->validate([
            'payment_method'    => 'required|in:card,cash',
            'items'             => 'required|array|min:1',
            'items.*.id'        => 'required|integer|exists:cart_items,id' . (Auth::check() ? ',user_id,' . Auth::id() : ''),
            'items.*.quantity'  => 'required|integer|min:1',
            'card_id'           => 'nullable|required_if:payment_method,card|exists:bank_cards,id,user_id,' . Auth::id(),
            'address_id'        => 'required|integer',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $user = Auth::user();

                $cartItems = $user
                    ->cartItems()
                    ->with('product')
                    ->whereIn('id', collect($request->input('items', []))->pluck('id'))
                    ->get();

                // Проверяем, что нашли все позиции
                if ($cartItems->count() !== count($request->input('items', []))) {
                    throw new Exception('Some items not found in cart');
                }

                // Итог по товарам с учётом скидок
                $itemsTotal = $cartItems->sum(function ($item) {
                    return $item->quantity * $this->discountedPrice($item->product);
                });

                $deliveryCost = self::DELIVERY_COST;

                // Формируем адрес доставки из first_delivery_point товаров (если нужно)
                // Исправлено: раньше брались product_ids из request (там id корзины), теперь из реальных cartItems
                $deliveryPoints = [];
                foreach ($cartItems as $item) {
                    $product = $item->product;
                    if ($product && $firstPoint = $product->first_delivery_point) {
                        $deliveryPoints[] = $this->formatDeliveryPoint($firstPoint);
                    }
                }
                $uniquePoints    = array_values(array_unique(array_filter($deliveryPoints)));
                $shippingAddress = !empty($uniquePoints) ? implode('; ', $uniquePoints) : 'Адрес не указан';

                // Создаем заказ (владелец — клиент!)
                $order = Order::create([
                    'uuid'            => (string)Str::uuid(),
                    'user_id'     => $user->id,
                    'total_amount'    => $itemsTotal + $deliveryCost,
                    'payment_method'  => $request->input('payment_method'),
                    'card_id'         => $request->input('card_id'),
                    'status'          => 'pending',
                    'shipping_address'=> $shippingAddress,
                    'user_address_id' => (int)$request->input('address_id'),
                ]);

                // Пакетная вставка позиций заказа
                $orderItems = $cartItems->map(function ($item) use ($order) {
                    $price = $this->discountedPrice($item->product);

                    return [
                        'order_id'   => $order->id,
                        'product_id' => $item->product_id,
                        'quantity'   => $item->quantity,
                        'price'      => $price,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();

                OrderItem::insert($orderItems);

                // Чистим корзину
                $user->cartItems()->whereIn('id', collect($request->input('items'))->pluck('id'))->delete();

                // Оплата
                $paymentResult = $this->processPayment($order, (string)$request->input('payment_method'), $request->input('card_id'));

                // Событие
                event(new OrderCreate($order));

                return response()->json([
                    'success'      => true,
                    'order'        => $order->load(['items.product']),
                    'payment_data' => $paymentResult,
                ], 201);
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'Order create failed',
            ], 422);
        }
    }

    /**
     * Показывает заказ. Доступ: владелец (customer) или продавец, у которого есть позиции в заказе.
     */
    public function show(Order $order): JsonResponse
    {
        $this->ensureCanViewOrder($order);

        return response()->json(
            $order->load([
                'user',
                'items.product.files',
            ])
        );
    }

    /**
     * Обновление полей заказа (только для клиента — владельца).
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'status'          => 'sometimes|string|in:pending,assembling,completed,canceled',
            'payment_method'  => 'sometimes|string|in:card,cash',
            'shipping_address'=> 'sometimes|string',
        ]);

        $order->update($validated);

        return response()->json($order->fresh());
    }

    /**
     * Генерация QR (только владелец-клиент).
     */
    public function generateQR(Order $order): JsonResponse
    {
        abort_if($order->user_id !== auth()->id(), 403);

        if (!in_array($order->status, self::QR_ALLOWED_STATUSES, true)) {
            return response()->json([
                'error' => 'QR-код можно сгенерировать только для заказов в статусах "assembling" или "shipped"',
            ], 400);
        }

        if (!$order->qr_token || now()->gt($order->expires_at)) {
            $order->update([
                'qr_token'   => Str::random(32),
                'expires_at' => now()->addHours(24),
            ]);
        }

        $url = route('order.status.update', [
            'order' => $order->id,
        ]);

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'   => QRCode::ECC_Q,
            'scale'      => 10,
        ]);

        $qrcode = (new QRCode($options))->render($url);

        return response()->json([
            'qrcode'     => $qrcode,
            'expires_at' => $order->expires_at,
        ]);
    }

    /**
     * Обновление статуса заказа (курьер/продавец по правилам + клиент не обновляет тут).
     */
    public function updateStatus(Order $order, Request $request): JsonResponse
    {
        Gate::authorize('updateStatus', $order);

        if (!Auth::check()) {
            Log::info('Unauthenticated user attempt');
            return response()->json(['error' => 'Требуется авторизация'], 401);
        }

        $user      = Auth::user();
        $isCourier = method_exists($user, 'isCourier') && $user->isCourier();
        $isSeller  = method_exists($user, 'isSeller') && $user->isSeller();

        $request->validate([
            'status'   => 'required|string|in:' . implode(',', self::ALLOWED_STATUSES_ALL),
            'qr_token' => 'nullable|string',
        ]);

        $oldStatus   = $order->status;
        $newStatus   = (string)$request->input('status');
        $qrRequired  = ($oldStatus === 'assembling' && $newStatus === 'shipped')
            || ($oldStatus === 'shipped' && $newStatus === 'completed');

        if ($qrRequired) {
            $request->validate(['qr_token' => 'required|string']);

            $qrToken = (string)$request->input('qr_token');

            if (!$order->qr_token || $order->qr_token !== $qrToken) {
                Log::info('Invalid QR token', [
                    'user_id'       => $user->id,
                    'order_id'      => $order->id,
                    'provided_token'=> $qrToken,
                    'expected_token'=> $order->qr_token,
                ]);
                return response()->json(['error' => 'Неверный QR-токен'], 401);
            }

            if (now()->gt($order->expires_at)) {
                Log::info('Expired QR token', [
                    'user_id'  => $user->id,
                    'order_id' => $order->id,
                ]);
                return response()->json(['error' => 'Срок действия QR-кода истек'], 400);
            }
        }

        // RateLimit для курьера
        if ($isCourier) {
            $key = 'courier_status_update_' . $user->id;
            RateLimiter::hit($key);

            if (RateLimiter::tooManyAttempts($key, 10)) {
                Log::info('Too many attempts by courier', ['courier_id' => $user->id]);
                return response()->json(['error' => 'Слишком много попыток'], 429);
            }
        }

        // Правила переходов для курьера
        if ($isCourier) {
            if ($order->courier_id !== $user->id) {
                Log::info('Courier not assigned to order', [
                    'courier_id'       => $user->id,
                    'order_courier_id' => $order->courier_id,
                ]);
                return response()->json(['error' => 'Заказ не назначен этому курьеру'], 403);
            }

            $allowedStatuses = ['shipped', 'completed'];
            if (!in_array($newStatus, $allowedStatuses, true)) {
                Log::info('Invalid status change by courier', [
                    'courier_id'     => $user->id,
                    'current_status' => $order->status,
                    'requested'      => $newStatus,
                ]);
                return response()->json(['error' => 'Курьер может менять статус только на "shipped" или "completed"'], 403);
            }

            if ($order->status === 'assembling' && $newStatus !== 'shipped') {
                return response()->json(['error' => 'Недопустимый переход: можно только "shipped"'], 403);
            }
            if ($order->status === 'shipped' && $newStatus !== 'completed') {
                return response()->json(['error' => 'Недопустимый переход: можно только "completed"'], 403);
            }
        }

        // Правила для продавца
        if ($isSeller) {
            // Продавец может менять только на assembling/canceled, и только если в заказе есть его позиции
            $hasSellerPositions = $order->items()->whereHas('product', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->exists();

            if (!$hasSellerPositions) {
                return response()->json(['error' => 'Нет прав на изменение этого заказа'], 403);
            }

            $allowedStatuses = ['assembling', 'canceled'];
            if (!in_array($newStatus, $allowedStatuses, true)) {
                Log::info('Invalid status change by seller', [
                    'seller_id'      => $user->id,
                    'current_status' => $order->status,
                    'requested'      => $newStatus,
                ]);
                return response()->json(['error' => 'Продавец может менять статус только на "assembling" или "canceled"'], 403);
            }
        }

        // Обновление
        $order->status = $newStatus;

        if ($qrRequired) {
            $order->qr_token   = null;
            $order->expires_at = null;
        }

        $order->save();

        event(new OrderStatusUpdated($order, $oldStatus, $newStatus));

        Log::info('Order status updated', [
            'order_id'  => $order->id,
            'from'      => $oldStatus,
            'to'        => $newStatus,
            'by_user'   => $user->id,
            'user_type' => $isCourier ? 'courier' : ($isSeller ? 'seller' : 'customer'),
        ]);

        return response()->json($order->fresh());
    }

    /* ===================== ВСПОМОГАТЕЛЬНОЕ ===================== */

    /** Единое место расчёта скидочной цены товара */
    private function discountedPrice(Product $product): float
    {
        $price = (float)$product->price;

        if ($product->discountType && $product->discountValue) {
            $type  = $product->discountType instanceof DiscountType
                ? $product->discountType->value
                : $product->discountType;

            $value = (float)$product->discountValue;

            if ($type === DiscountType::PERCENT->value || $type === 'percent') {
                $price -= $price * ($value / 100);
            } elseif ($type === DiscountType::FIXED_SUM->value || $type === 'fixedSum' || $type === 'fixed_sum') {
                $price -= $value;
            }
        }

        return max(0.0, round($price, 2));
    }

    private function formatDeliveryPoint(array $point): string
    {
        $parts = [];
        if (!empty($point['city']))    { $parts[] = $point['city']; }
        if (!empty($point['name']))    { $parts[] = $point['name']; }
        if (!empty($point['location'])){ $parts[] = $point['location']; }

        return implode(', ', $parts);
    }

    private function processPayment(Order $order, string $paymentMethod, ?int $cardId): array
    {
        switch ($paymentMethod) {
            case 'card':
                /** @var BankCard|null $card */
                $card = $cardId ? BankCard::find($cardId) : null;

                if (!$card || $card->user_id !== Auth::id()) {
                    throw new InvalidArgumentException('Invalid card');
                }

                return [
                    'type'           => 'card',
                    'amount'         => (float)$order->total_amount,
                    'card_last_four' => $card->last_four,
                    // TODO: интеграция с платёжным провайдером
                ];

            case 'cash':
                return [
                    'type'    => 'cash',
                    'message' => 'Оплата при получении',
                ];

            default:
                throw new InvalidArgumentException('Unsupported payment method');
        }
    }

    /**
     * Авторизация просмотра: владелец (customer) или продавец, имеющий позиции в заказе.
     */
    private function ensureCanViewOrder(Order $order): void
    {
        $userId = (int)auth()->id();

        if ($order->user_id === $userId) {
            return;
        }

        $isSellerOfThisOrder = $order->items()
            ->whereHas('product', static function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->exists();

        abort_if(!$isSellerOfThisOrder, 403);
    }
}
