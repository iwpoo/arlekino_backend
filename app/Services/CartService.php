<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CartService
{
    public function __construct(
        protected CurrencyConverter $currencyConverter
    ) {}

    public function getCartItems(User $user): array
    {
        $userId = $user->id;
        $preferredCurrency = strtoupper($user->currency ?? $this->currencyConverter->getBaseCurrency());

        $cacheKey = "user_cart_{$userId}_$preferredCurrency";

        return Cache::tags(['cart', "user_$userId"])->remember($cacheKey, 600, function () use ($user, $preferredCurrency) {
            $items = $user->cartItems()
                ->with([
                    'product.user:id,currency',
                    'product.files',
                    'product.promotions' => fn($q) => $q->active()
                ])
                ->get();

            $totalPrice = 0;
            $totalQuantity = 0;

            foreach ($items as $item) {
                $product = $item->product;

                $priceInOriginalCurrency = $product->getFinalPrice();

                $convertedPrice = $this->currencyConverter->convert(
                    $priceInOriginalCurrency,
                    $product->user->currency,
                    $preferredCurrency
                );

                $product->setAttribute('converted_price', $convertedPrice);
                $product->setAttribute('currency', $preferredCurrency);

                $totalPrice += ($convertedPrice * $item->quantity);
                $totalQuantity += $item->quantity;
            }

            return [
                'items' => $items,
                'totalQuantity' => $totalQuantity,
                'totalPrice' => round($totalPrice, 2),
                'currency' => $preferredCurrency
            ];
        });
    }

    public function addItem(User $user, array $data): CartItem
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                $cartItem = $user->cartItems()->updateOrCreate(
                    ['product_id' => $data['product_id']],
                    ['quantity' => DB::raw("quantity + {$data['quantity']}")]
                );

                $this->clearCartCache($user->id);

                return $cartItem->load('product');
            });
        } catch (QueryException $e) {
            Log::error("Database error while adding to cart: " . $e->getMessage(), [
                'user_id' => $user->id,
                'data' => $data
            ]);
            throw new RuntimeException("Ошибка базы данных при обновлении корзины.");
        } catch (Throwable $e) {
            Log::critical("Unexpected cart error: " . $e->getMessage());
            throw new RuntimeException("Не удалось обновить корзину");
        }
    }

    public function updateQuantity(CartItem $cartItem, int $quantity): CartItem
    {
        $cartItem->update(['quantity' => $quantity]);
        return $cartItem;
    }

    public function removeItem(CartItem $cartItem): bool
    {
        $userId = $cartItem->user_id;
        $deleted = $cartItem->delete();

        if ($deleted) {
            $this->clearCartCache($userId);
        }

        return (bool) $deleted;
    }

    private function clearCartCache(int $userId): void
    {
        Cache::tags(["user_$userId"])->flush();
    }
}
