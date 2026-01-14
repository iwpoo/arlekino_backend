<?php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
use Throwable;

class RefreshCurrencyRates extends Command
{
    protected $signature = 'currency:refresh';
    protected $description = 'Refresh currency rates from ExchangeRate-API for CIS countries';

    public function handle(): int
    {
        $base = strtoupper(config('app.base_currency', 'RUB'));

        $this->info("Starting refresh. Base currency: $base");

        $url = 'https://www.cbr.ru/scripts/XML_daily.asp';

        try {
            $response = Http::timeout(20)->get($url);

            if (!$response->ok()) {
                $this->error("CBR API is unavailable. Status: " . $response->status());
                return self::FAILURE;
            }

            $xml = new SimpleXMLElement($response->body());
            $rawRates = [];

            foreach ($xml->Valute as $valute) {
                $code = (string)$valute->CharCode;
                $nominal = (float)str_replace(',', '.', (string)$valute->Nominal);
                $value = (float)str_replace(',', '.', (string)$valute->Value);

                $rawRates[$code] = $value / $nominal;
            }
            $rawRates['RUB'] = 1.0;

            if (!isset($rawRates[$base])) {
                $this->error("Base currency $base not found in CBR data.");
                return self::FAILURE;
            }

            $baseInRub = $rawRates[$base];

            $symbols = [
                'USD' => '$', 'EUR' => '€', 'RUB' => '₽', 'KGS' => 'с',
                'KZT' => '₸', 'UZS' => 'so\'m', 'BYN' => 'Br', 'AMD' => '֏'
            ];

            $upsertData = [];
            foreach ($rawRates as $code => $rateInRub) {
                $rateToBase = $rateInRub / $baseInRub;

                $upsertData[] = [
                    'code' => $code,
                    'name' => $code,
                    'symbol' => $symbols[$code] ?? $code,
                    'rate_to_base' => (float)$rateToBase,
                    'is_active' => true,
                    'updated_at' => now(),
                ];
            }

            DB::transaction(function () use ($upsertData) {
                Currency::upsert($upsertData, ['code'], ['rate_to_base', 'updated_at', 'is_active']);
            });

            $this->info("Success! Rates updated and cached relative to $base.");
            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error("Error: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
