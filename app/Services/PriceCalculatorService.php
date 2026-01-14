<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class PriceCalculatorService
{
    public function __construct(protected CurrencyConverter $currencyConverter) {}

    public function getItemPrice(Product $product): float
    {
        $bestPromotion = $product->getBestPromotion();
        if ($bestPromotion) {
            return $bestPromotion->calculateDiscountedPrice((float)$product->price);
        }

        $price = (float)$product->price;
        if ($product->discount_type && $product->discount_value) {
            $value = (float)$product->discount_value;
            $price = match ($product->discount_type) {
                'percent' => $price * (1 - $value / 100),
                'fixed_sum' => $price - $value,
                default => $price,
            };
        }

        return max(0.0, round($price, 2));
    }

    public function calculateTotals(Collection $cartItems, string $targetCurrency): array
    {
        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        $itemsTotalBase = $cartItems->sum(fn($item) => $this->getItemPrice($item->product) * $item->quantity);

        $deliveryBase = Cache::remember("delivery_base_$baseCurrency", 3600, function() use ($baseCurrency) {
            return $this->currencyConverter->convert(config('services.price_calculator.delivery_cost', 5.0), config('app.base_currency'), $baseCurrency);
        });

        $itemsTotalConverted = $this->currencyConverter->convert($itemsTotalBase, $baseCurrency, $targetCurrency);
        $deliveryConverted = $this->currencyConverter->convert($deliveryBase, $baseCurrency, $targetCurrency);

        return [
            'items' => round($itemsTotalConverted, 2),
            'delivery' => round($deliveryConverted, 2),
            'total' => round($itemsTotalConverted + $deliveryConverted, 2),
            'currency' => $targetCurrency,
        ];
    }

    public function convertModelPrices(object $model, string $toCurrency): object
    {
        $base = $this->currencyConverter->getBaseCurrency();
        if ($toCurrency === $base) return $model;

        $model->original_total_amount = $model->total_amount;
        $model->total_amount = $this->currencyConverter->convert((float)$model->total_amount, $base, $toCurrency);
        $model->currency = $toCurrency;

        return $model;
    }
}
