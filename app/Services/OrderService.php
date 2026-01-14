<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderCreate;
use App\Events\OrderStatusUpdated;
use App\Events\SellerOrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\User;
use DomainException;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OrderService
{
    public function __construct(
        protected CurrencyConverter      $currencyConverter,
        protected PriceCalculatorService $priceCalculator
    ) {}

    public function getOrders(User $user, int $perPage = 10): array
    {
        $currency = $user->currency ?? $this->currencyConverter->getBaseCurrency();

        $query = $user->isSeller()
            ? SellerOrder::where('seller_id', $user->id)->with(['order', 'items.product.files'])
            : Order::where('user_id', $user->id)->with(['user', 'items.product.files', 'sellerOrders.seller']);

        $paginated = $query->latest('id')->paginate($perPage);
        $paginated->getCollection()->transform(fn($order) => $this->priceCalculator->convertModelPrices($order, $currency));

        return ['success' => true, 'data' => $paginated];
    }

    public function getOrder(int $id, User $user): array
    {
        $currency = $user->currency ?? $this->currencyConverter->getBaseCurrency();

        $order = $user->isSeller()
            ? SellerOrder::with(['order', 'items.product.files'])->where('seller_id', $user->id)->findOrFail($id)
            : Order::with(['user', 'items.product.files', 'sellerOrders.seller'])->where('user_id', $user->id)->findOrFail($id);

        return ['success' => true, 'data' => $this->priceCalculator->convertModelPrices($order, $currency)];
    }

    public function precalculate(User $user, array $itemIds, ?string $preferredCurrency): array
    {
        $currency = $preferredCurrency ?? $user->currency ?? $this->currencyConverter->getBaseCurrency();

        $cartItems = $user->cartItems()
            ->with(['product.promotions'])
            ->whereIn('id', $itemIds)
            ->get();

        return $this->priceCalculator->calculateTotals($cartItems, $currency);
    }

    public function processOrderCreation(array $validatedData, User $user): array
    {
        try {
            return DB::transaction(function () use ($validatedData, $user) {
                $cartItems = $user->cartItems()
                    ->with(['product.promotions'])
                    ->whereIn('id', collect($validatedData['items'])->pluck('id'))
                    ->lockForUpdate()
                    ->get();

                if ($cartItems->count() !== count($validatedData['items'])) {
                    throw new Exception('Cart mismatch');
                }

                foreach ($cartItems as $item) {
                    if ($item->product->quantity < $item->quantity) {
                        throw new Exception("Товара {$item->product->title} недостаточно на складе");
                    }
                }

                $baseCurrency = $this->currencyConverter->getBaseCurrency();
                $deliveryCost = $this->currencyConverter->convert(config('services.order.delivery_cost'), config('app.base_currency'), $baseCurrency);

                $shippingAddress = $cartItems->map(fn($item) => $item->product->first_delivery_point_string)->filter()->unique()->implode('; ') ?: 'N/A';

                $order = Order::create([
                    'user_id' => $user->id,
                    'total_amount' => 0,
                    'payment_method' => $validatedData['payment_method'],
                    'status' => OrderStatus::PENDING->value,
                    'shipping_address' => $shippingAddress,
                    'user_address_id' => $validatedData['address_id'],
                ]);

                $totalSum = 0;
                $groupedItems = $cartItems->groupBy(fn($item) => $item->product->user_id);

                foreach ($groupedItems as $sellerId => $items) {
                    $sellerSubtotal = $items->sum(fn($it) => $this->priceCalculator->getItemPrice($it->product) * $it->quantity);

                    $sellerOrder = SellerOrder::create([
                        'order_id' => $order->id,
                        'seller_id' => $sellerId,
                        'total_amount' => $sellerSubtotal,
                        'status' => OrderStatus::PENDING->value,
                    ]);

                    foreach ($items as $item) {
                        $item->product->decrement('quantity', $item->quantity);

                        OrderItem::create([
                            'order_id' => $order->id,
                            'seller_order_id' => $sellerOrder->id,
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                            'price' => $this->priceCalculator->getItemPrice($item->product),
                        ]);
                    }
                    $totalSum += $sellerSubtotal;
                }

                $order->update(['total_amount' => $totalSum + $deliveryCost]);

                $user->cartItems()->whereIn('id', $cartItems->pluck('id'))->delete();

                event(new OrderCreate($user, $order));

                return ['success' => true, 'order' => $order->load('items.product')];
            });
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::critical("Order Creation Failure: " . $e->getMessage(), [
                'user_id' => $user->id,
                'input' => $validatedData,
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException("Ошибка при обработке заказа. Пожалуйста, обратитесь в поддержку.");
        }
    }

    public function processOrderStatusUpdate(Order $order, array $data, User $user): array
    {
        $newStatus = $data['status'];
        $sellerOrderId = $data['seller_order_id'] ?? null;

        if ($sellerOrderId) {
            $sellerOrder = SellerOrder::where('id', $sellerOrderId)->where('order_id', $order->id)->firstOrFail();

            if (in_array($newStatus, [OrderStatus::SHIPPED->value, OrderStatus::COMPLETED->value])) {
                if (!$order->qr_token || $order->qr_token !== ($data['qr_token'] ?? null) || now()->gt($order->expires_at)) {
                    return ['error' => 'Invalid or expired QR token'];
                }
            }

            $oldStatus = $sellerOrder->status;
            $sellerOrder->update(['status' => $newStatus]);

            event(new SellerOrderStatusUpdated($user, $sellerOrder, $oldStatus, $newStatus));
            $this->aggregateOrderStatus($order);

            return ['success' => true, 'seller_order' => $sellerOrder->fresh(), 'order' => $order->fresh()];
        }

        $order->update(['status' => $newStatus]);
        return ['success' => true, 'order' => $order->fresh()];
    }

    private function aggregateOrderStatus(Order $order): void
    {
        $statuses = $order->sellerOrders()->distinct()->pluck('status');

        $calculated = match (true) {
            $statuses->every(fn($s) => $s === OrderStatus::COMPLETED->value) => OrderStatus::COMPLETED,
            $statuses->every(fn($s) => $s === OrderStatus::CANCELED->value) => OrderStatus::CANCELED,
            $statuses->contains(OrderStatus::ASSEMBLING->value) => OrderStatus::ASSEMBLING,
            $statuses->contains(OrderStatus::SHIPPED->value) => OrderStatus::SHIPPED,
            default => OrderStatus::PENDING,
        };

        if ($order->status !== $calculated->value) {
            $order->update(['status' => $calculated->value]);
            event(new OrderStatusUpdated(Auth::user(), $order, $order->status, $calculated->value));
        }
    }
}
