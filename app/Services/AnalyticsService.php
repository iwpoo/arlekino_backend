<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Post;
use App\Models\Review;
use App\Models\Follow;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function __construct(
        protected CurrencyConverter $currencyConverter
    ) {}

    public function getAnalyticsData(User $user, string $periodName): array
    {
        $currency = $user->currency ?? $this->currencyConverter->getBaseCurrency();
        $cacheKey = "seller_analytics:$user->id:$periodName:$currency";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($user, $periodName) {
            return $this->calculateRawData($user, $periodName);
        });
    }

    public function calculateRawData(User $user, string $periodName): array
    {
        $range = $this->getDateRange($periodName);
        $currency = $user->currency ?? $this->currencyConverter->getBaseCurrency();

        return [
            'sales'    => $this->getSalesData($user->id, $range, $currency),
            'products' => $this->getProductsData($user->id, $currency),
            'audience' => $this->getAudienceData($user->id),
            'content'  => $this->getContentData($user->id, $range),
            'reviews'  => $this->getReviewsData($user->id),
            'period'   => $periodName,
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    private function getDateRange(string $period): array
    {
        $now = now();
        return match ($period) {
            'day'   => [$now->startOfDay()->toDateTimeString(), $now->endOfDay()->toDateTimeString()],
            'week'  => [$now->startOfWeek()->toDateTimeString(), $now->endOfWeek()->toDateTimeString()],
            'year'  => [$now->startOfYear()->toDateTimeString(), $now->endOfYear()->toDateTimeString()],
            default => [$now->startOfMonth()->toDateTimeString(), $now->endOfMonth()->toDateTimeString()],
        };
    }

    private function getSalesData(int $sellerId, array $range, string $currency): array
    {
        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        $totals = SellerOrder::where('seller_id', $sellerId)
            ->whereBetween('created_at', $range)
            ->selectRaw('
                COUNT(*) as total_count,
                COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_count,
                SUM(CASE WHEN status = "completed" THEN total_amount ELSE 0 END) as total_revenue
            ')
            ->first();

        $revenue = (float) $totals->total_revenue;
        $convertedRevenue = $currency !== $baseCurrency
            ? $this->currencyConverter->convert($revenue, $baseCurrency, $currency)
            : $revenue;

        $avgOrder = $totals->completed_count > 0 ? $convertedRevenue / $totals->completed_count : 0;

        $salesByDay = SellerOrder::where('seller_id', $sellerId)
            ->where('status', 'completed')
            ->whereBetween('created_at', $range)
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($day) => [
                'date' => $day->date,
                'revenue' => $currency !== $baseCurrency
                    ? $this->currencyConverter->convert((float)$day->revenue, $baseCurrency, $currency)
                    : (float)$day->revenue,
                'orders' => $day->orders,
            ]);

        return [
            'total_revenue'   => round($convertedRevenue, 2),
            'currency'        => $currency,
            'total_orders'    => $totals->total_count,
            'completed_orders'=> $totals->completed_count,
            'average_order'   => round($avgOrder, 2),
            'sales_by_day'    => $salesByDay,
        ];
    }

    private function getProductsData(int $sellerId, string $currency): array
    {
        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        $topProducts = Product::where('user_id', $sellerId)
            ->withCount(['orderItems as sales_count' => function ($query) {
                $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('orders.status', 'completed');
            }])
            ->orderByDesc('sales_count')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'price' => $currency !== $baseCurrency
                    ? $this->currencyConverter->convert((float)$p->price, $baseCurrency, $currency)
                    : (float)$p->price,
                'sales_count' => $p->sales_count,
            ]);

        return [
            'top_products'   => $topProducts,
            'total_products' => Product::where('user_id', $sellerId)->count(),
        ];
    }

    private function getAudienceData(int $sellerId): array
    {
        $totalFollowers = Follow::where('following_id', $sellerId)->count();

        $convertingFollowers = DB::table('follows')
            ->join('orders', 'follows.follower_id', '=', 'orders.user_id')
            ->join('seller_orders', 'orders.id', '=', 'seller_orders.order_id')
            ->where('follows.following_id', $sellerId)
            ->where('seller_orders.seller_id', $sellerId)
            ->where('seller_orders.status', 'completed')
            ->distinct()
            ->count('follows.follower_id');

        return [
            'total_followers' => $totalFollowers,
            'conversion_rate' => $totalFollowers > 0 ? round(($convertingFollowers / $totalFollowers) * 100, 2) : 0,
        ];
    }

    private function getContentData(int $sellerId, array $range): array
    {
        $stats = Post::where('user_id', $sellerId)
            ->whereBetween('created_at', $range)
            ->selectRaw('
                COUNT(*) as count,
                SUM(views_count) as views,
                SUM(likes_count + comments_count + shares_count) as engagement
            ')
            ->first();

        $topPost = Post::where('user_id', $sellerId)
            ->whereBetween('created_at', $range)
            ->orderByDesc('likes_count')
            ->first(['id', 'content', 'likes_count', 'views_count']);

        return [
            'total_posts'     => $stats->count,
            'engagement_rate' => $stats->views > 0 ? round(($stats->engagement / $stats->views) * 100, 2) : 0,
            'top_post'        => $topPost,
        ];
    }

    private function getReviewsData(int $sellerId): array
    {
        $productIds = Product::where('user_id', $sellerId)->select('id');

        $stats = Review::whereIn('product_id', $productIds)
            ->selectRaw('
                AVG(rating) as avg_rating,
                COUNT(*) as total,
                COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive,
                COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative
            ')
            ->first();

        return [
            'average_rating'   => round($stats->avg_rating, 2),
            'total_reviews'    => $stats->total,
            'positive_reviews' => $stats->positive,
            'negative_reviews' => $stats->negative,
        ];
    }
}
