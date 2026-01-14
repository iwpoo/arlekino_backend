<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class PrecomputeSellerAnalytics implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 600;

    public function __construct(
        protected User $seller,
        protected string $period = 'month'
    ) {
        $this->onQueue('low');
    }

    public function handle(AnalyticsService $service): void
    {
        $data = $service->calculateRawData($this->seller, $this->period);

        $currency = $this->seller->currency ?? config('app.base_currency');
        $cacheKey = "seller_analytics:{$this->seller->id}:$this->period:$currency";

        Cache::put($cacheKey, $data, now()->addHours(24));
    }
}
