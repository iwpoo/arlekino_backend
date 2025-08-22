<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\v1;

use App\Enums\DiscountType;
use App\Events\OrderCreate;
use App\Events\OrderStatusUpdated;
use App\Events\SellerOrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\BankCard;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerOrder;
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

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->isSeller()) {
            $query = SellerOrder::query()
                ->where('seller_id', $user->id)
                ->with([
                    'order:id,user_id,total_amount,status,created_at',
                    'items.product.files',
                ])
                ->latest('id');

            $perPage = (int)$request->input('per_page', 10);
            $paginated = $query->paginate($perPage);

            return response()->json($paginated);
        }

        // Обычный список заказов для клиентов
        $orders = Order::query()
            ->with([
                'user:id,name,email',
                'items.product.files',
                'sellerOrders.seller:id,name,email',
                'sellerOrders.items.product:id,title',
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

                $orderItemsData = [];
                foreach ($cartItems as $item) {
                    $price = $this->discountedPrice($item->product);

                    $orderItemsData[] = [
                        'order_id'        => $order->id,
                        'seller_order_id' => null,
                        'product_id'      => $item->product_id,
                        'quantity'        => $item->quantity,
                        'price'           => $price,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ];
                }

                OrderItem::insert($orderItemsData);

                $items = $order->items()->with('product')->get();

                $grouped = [];
                foreach ($items as $it) {
                    $sellerId = (int)$it->product->user_id;
                    $grouped[$sellerId][] = $it;
                }

                foreach ($grouped as $sellerId => $itemsForSeller) {
                    $sellerTotal = 0;
                    foreach ($itemsForSeller as $it) {
                        $sellerTotal += $it->price * $it->quantity;
                    }

                    $sellerOrder = \App\Models\SellerOrder::create([
                        'order_id'     => $order->id,
                        'seller_id'    => $sellerId,
                        'total_amount' => $sellerTotal,
                        'status'       => 'pending',
                    ]);

                    $itemIds = collect($itemsForSeller)->pluck('id')->toArray();
                    OrderItem::whereIn('id', $itemIds)->update(['seller_order_id' => $sellerOrder->id]);

                    // Можно тут отправить событие продавцу, уведомление и т.д.
                    // event(new SellerOrderCreated($sellerOrder));
                }

                $order->refresh();
                $totalItems = $order->items->sum(function($it){ return $it->price * $it->quantity; });
                $order->total_amount = $totalItems + $deliveryCost;
                $order->save();

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

    public function show(int $id): JsonResponse
    {
        $user = auth()->user();

        if ($user->isClient()) {
            $order = Order::with(['user', 'items.product.files'])->findOrFail($id);
        } elseif ($user->isSeller()) {
            $order = SellerOrder::with(['order', 'items.product.files'])->findOrFail($id);
        } else {
            abort(403, 'Unauthorized');
        }

        return response()->json($order);
    }

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

    public function generateQR(Order $order): JsonResponse
    {
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

    public function updateStatus(Order $order, Request $request): JsonResponse
    {
        Gate::authorize('updateStatus', $order);

        if (!Auth::check()) {
            Log::info('Unauthenticated user attempt');
            return response()->json(['error' => 'Требуется авторизация'], 401);
        }

        $user      = Auth::user();
        $isCourier = $user->isCourier();
        $isSeller  = $user->isSeller();
        $isCustomer = $user->isClient() && $order->user_id === $user->id;

        $request->validate([
            'status'   => 'required|string|in:' . implode(',', self::ALLOWED_STATUSES_ALL),
            'qr_token' => 'nullable|string',
            'seller_order_id' => 'nullable|integer|exists:seller_orders,id,order_id,' . $order->id,
        ]);

        $newStatus = (string)$request->input('status');
        $sellerOrderId = $request->input('seller_order_id');

        if ($sellerOrderId) {
            $sellerOrder = SellerOrder::where('id', $sellerOrderId)->where('order_id', $order->id)->firstOrFail();
            $oldStatus = $sellerOrder->status;

            // Правила для продавца: может менять только свой seller_order
            if ($isSeller) {
                if ($sellerOrder->seller_id !== $user->id) {
                    return response()->json(['error' => 'Нет прав на изменение этого подзаказа'], 403);
                }

                $allowed = ['assembling', 'canceled', 'pending'];
                if (!in_array($newStatus, $allowed, true)) {
                    Log::info('Invalid status change by seller', ['seller_id' => $user->id, 'requested' => $newStatus]);
                    return response()->json(['error' => 'Продавец может менять подзаказ только на "assembling" или "canceled"'], 403);
                }
            }

            // Правила для курьера: курьер должен быть привязан к sellerOrder (предполагаем поле courier_id)
            if ($isCourier) {
                if ($sellerOrder->courier_id !== $user->id) {
                    return response()->json(['error' => 'Подзаказ не назначен этому курьеру'], 403);
                }

                $allowed = ['shipped', 'completed'];
                if (!in_array($newStatus, $allowed, true)) {
                    return response()->json(['error' => 'Курьер может менять подзаказ только на "shipped" или "completed"'], 403);
                }
            }

            // QR проверка — если требуется (переход assembling->shipped или shipped->completed)
            $qrRequired = ($oldStatus === 'assembling' && $newStatus === 'shipped')
                || ($oldStatus === 'shipped' && $newStatus === 'completed');

            if ($qrRequired) {
                $request->validate(['qr_token' => 'required|string']);
                $qrToken = (string)$request->input('qr_token');

                if (!$order->qr_token || $order->qr_token !== $qrToken) {
                    return response()->json(['error' => 'Неверный QR-токен'], 401);
                }

                if (now()->gt($order->expires_at)) {
                    return response()->json(['error' => 'Срок действия QR-кода истек'], 400);
                }
            }

            $sellerOrder->status = $newStatus;
            $sellerOrder->save();

            event(new SellerOrderStatusUpdated(auth()->user(), $sellerOrder, $oldStatus, $newStatus));
            Log::info('SellerOrder status updated', ['seller_order_id' => $sellerOrder->id, 'from' => $oldStatus, 'to' => $newStatus, 'by' => $user->id]);

            // После изменения — агрегируем статус глобального заказа
            $this->aggregateOrderStatus($order);

            return response()->json([
                'success' => true,
                'seller_order' => $sellerOrder->fresh(),
                'order' => $order->fresh()->load('sellerOrders'),
            ]);
        }

        $oldOrderStatus = $order->status;

        // Правила для курьера (глобально): курьер должен быть назначен на order
        if ($isCourier) {
            if ($order->courier_id !== $user->id) {
                return response()->json(['error' => 'Заказ не назначен этому курьеру'], 403);
            }

            $allowed = ['shipped', 'completed'];
            if (!in_array($newStatus, $allowed, true)) {
                return response()->json(['error' => 'Курьер может менять статус заказа только на "shipped" или "completed"'], 403);
            }
        }

        // Клиент/владелец не должен менять статусы тут
        if ($isCustomer && !$isCourier && !$isSeller) {
            return response()->json(['error' => 'Клиент не может менять статус заказа здесь'], 403);
        }

        // QR для переходов, если нужно
        $qrRequired = ($oldOrderStatus === 'assembling' && $newStatus === 'shipped')
            || ($oldOrderStatus === 'shipped' && $newStatus === 'completed');

        if ($qrRequired) {
            $request->validate(['qr_token' => 'required|string']);
            $qrToken = (string)$request->input('qr_token');

            if (!$order->qr_token || $order->qr_token !== $qrToken) {
                return response()->json(['error' => 'Неверный QR-токен'], 401);
            }

            if (now()->gt($order->expires_at)) {
                return response()->json(['error' => 'Срок действия QR-кода истек'], 400);
            }
        }

        // Обновляем глобальный order
        $order->status = $newStatus;
        if ($qrRequired) {
            $order->qr_token = null;
            $order->expires_at = null;
        }
        $order->save();

        event(new OrderStatusUpdated(auth()->user(), $order, $oldOrderStatus, $newStatus));

        return response()->json($order->fresh());
    }

    /* ===================== ВСПОМОГАТЕЛЬНОЕ ===================== */

    private function aggregateOrderStatus(Order $order): void
    {
        $order->load('sellerOrders');

        $statuses = $order->sellerOrders->pluck('status')->unique()->toArray();

        if ($order->sellerOrders->count() > 0) {
            if ($order->sellerOrders->every(fn($s) => $s->status === 'completed')) {
                $new = 'completed';
            } elseif ($order->sellerOrders->every(fn($s) => $s->status === 'canceled')) {
                $new = 'canceled';
            } elseif (in_array('assembling', $statuses, true)) {
                $new = 'assembling';
            } elseif (in_array('shipped', $statuses, true)) {
                $new = 'shipped';
            } else {
                $new = 'pending';
            }

            if ($order->status !== $new) {
                $old = $order->status;
                $order->status = $new;
                $order->save();
                event(new OrderStatusUpdated(auth()->user(), $order, $old, $new));
            }
        }
    }

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
}
