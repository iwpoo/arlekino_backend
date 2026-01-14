<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class UpdateProductRatingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(protected int $productId) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $stats = Review::where('product_id', $this->productId)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
            ->first();

        $product = Product::where('id', $this->productId)->first(['id', 'user_id']);

        if ($product) {
            $product->update([
                'rating' => round($stats->avg_rating ?? 0, 2),
                'reviews_count' => $stats->total ?? 0
            ]);

            Cache::forget("seller_rating_$product->user_id");

            SyncEntityToElasticsearch::dispatch($product);
        }

        Cache::forget("seller_rating_$product->user_id");
    }
}
