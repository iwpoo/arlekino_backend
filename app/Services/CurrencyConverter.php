<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CurrencyConverter
{
    protected const CACHE_KEY = 'currency_rates';
    protected const CACHE_TTL = 3600;

    public function getBaseCurrency(): string
    {
        return config('app.base_currency', 'RUB');
    }

    public function getRates(): array
    {
        return Cache::remember(self::CACHE_KEY,self::CACHE_TTL, function () {
            return Currency::where('is_active', true)
                ->pluck('rate_to_base', 'code')
                ->all();
        });
    }

    public function convert(float $amount, ?string $from, string $to): float
    {
        $rates = $this->getRates();
        $base  = $this->getBaseCurrency();
        $from  = $from ? strtoupper($from) : $base;
        $to    = strtoupper($to);

        if ($from === $to) return round($amount, 2);

        if (!isset($rates[$from]) || !isset($rates[$to])) {
            Log::critical("Currency mismatch: Attempted to convert from $from to $to, but rates are missing.");
            throw new InvalidArgumentException("Курс для валюты $from или $to не найден.");
        }

        if ($rates[$from] <= 0 || $rates[$to] <= 0) {
            Log::emergency("Critical error: Currency rate for $from or $to is zero!");
            throw new InvalidArgumentException("Техническая ошибка при расчете стоимости.");
        }

        $priceInBase = $amount * $rates[$from];
        $finalPrice = $priceInBase / $rates[$to];

        return round($finalPrice, 2);
    }

    public function listRates(): array
    {
        return $this->getRates();
    }
}
