<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Review;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class DashboardService
{
    public function __construct(
        protected CurrencyConverter $currencyConverter
    ) {}

    public function getSellerMetrics(User $user, string $period): array
    {
        $baseCurrency = $this->currencyConverter->getBaseCurrency();
        $targetCurrency = $user->currency ?? $baseCurrency;

        $cacheKey = "seller_metrics_base_{$user->id}_$period";

        $data = Cache::remember($cacheKey, 300, function() use ($user, $period) {
            $range = $this->calculateDateRange($period);

            return [
                'kpi'      => $this->fetchKPI($user->id, $range),
                'activity' => $this->fetchActivityFeed($user->id),
                'period'   => $period,
            ];
        });

        $data['kpi']['revenue']['value'] = $this->currencyConverter->convert(
            $data['kpi']['revenue']['value'],
            $baseCurrency,
            $targetCurrency
        );
        $data['kpi']['revenue']['currency'] = $targetCurrency;

        return $data;
    }

    private function fetchKPI(int $sellerId, array $range): array
    {
        [$start, $end] = $range;

        $stats = SellerOrder::where('seller_id', $sellerId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as revenue,
                COUNT(*) as total_orders
            ")->first();

        $totalViews = Product::where('user_id', $sellerId)->sum('views_count');

        return [
            'revenue' => [
                'value' => (float) ($stats->revenue ?? 0),
                'currency' => null
            ],
            'orders_count' => (int) ($stats->total_orders ?? 0),
            'views' => (int) $totalViews,
        ];
    }

    private function fetchActivityFeed(int $sellerId): array
    {
        $orders = SellerOrder::where('seller_id', $sellerId)
            ->with(['order.user'])
            ->latest()->limit(5)->get();

        $reviews = Review::whereHas('product', fn($q) => $q->where('user_id', $sellerId))
            ->with(['user', 'product'])
            ->latest()->limit(5)->get();

        return collect($orders)->merge($reviews)->sortByDesc('created_at')->values()->all();
    }

    public function confirmOrder(SellerOrder $sellerOrder): SellerOrder
    {
        if (!in_array($sellerOrder->status, ['pending', 'assembling'])) {
            throw ValidationException::withMessages([
                'order' => 'Этот заказ нельзя подтвердить, так как он находится в статусе: ' . $sellerOrder->status
            ]);
        }

        $sellerOrder->update([
            'status' => 'assembling',
            'confirmed_at' => now(),
        ]);

        return $sellerOrder->load(['order.user', 'items.product']);
    }

    private function calculateDateRange(string $period): array
    {
        $now = now();
        return match($period) {
            'week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }
}
