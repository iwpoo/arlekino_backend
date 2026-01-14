<?php

namespace App\Services;

use App\Jobs\ProcessReviewMediaJob;
use App\Jobs\UpdateProductRatingJob;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ReviewService
{
    public function getReviews(Product $product, ?User $user): LengthAwarePaginator
    {
        $query = $product->reviews()->with('user')->latest();

        if ($user) {
            $query->withExists(['helpfulUsers as is_helpful' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);
        }

        return $query->paginate(10);
    }

    public function getProductsToReview(User $user): Collection
    {
        if (!$user->isClient()) return collect();

        $cacheKey = "user_{$user->id}_pending_reviews";

        return Cache::remember($cacheKey, 3600, function() use ($user) {
            return Product::whereIn('id', function($query) use ($user) {
                $query->select('product_id')
                    ->from('order_items')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->where('orders.user_id', $user->id)
                    ->where('orders.status', 'completed')
                    ->where('orders.created_at', '>=', now()->subDays(30));
            })
                ->whereNotIn('id', Review::where('user_id', $user->id)->select('product_id'))
                ->with('files')
                ->latest()
                ->limit(5)
                ->get();
        });
    }

    public function createReview(Product $product, User $user, array $data, $files = []): Review
    {
        if (Review::where('user_id', $user->id)->where('product_id', $product->id)->exists()) {
            throw new RuntimeException('Вы уже оставили отзыв', 422);
        }

        if (!$this->hasPurchased($user, $product)) {
            throw new RuntimeException('Отзыв доступен только после покупки', 403);
        }

        try {
            return DB::transaction(function () use ($product, $user, $data, $files) {
                $review = Review::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'rating' => $data['rating'],
                    'comment' => $data['comment'] ?? null,
                    'is_verified_purchase' => $this->hasPurchased($user, $product),
                ]);

                if (!empty($files['photos']) || !empty($files['video'])) {
                    $paths = $this->storeTemporaryFiles($files);
                    ProcessReviewMediaJob::dispatch($review->id, $paths);
                }

                $this->updateProductStats($review->product);

                Cache::forget("user_{$user->id}_pending_reviews");

                return $review->load('user');
            });
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("Review Creation Error [Product: $product->id]: " . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException("Не удалось сохранить отзыв. Попробуйте еще раз позже.");
        }
    }

    public function updateReview(Review $review, array $data, $files = []): Review
    {
        try {
            return DB::transaction(function () use ($review, $data, $files) {
                if (!empty($files['photos'])) {
                    $this->deleteMedia($review->photos);
                    $review->photos = $this->uploadMedia($files['photos'], 'review_photos');
                }

                if ($files['video']) {
                    if ($review->video_path) $this->deleteMedia([$review->video_path]);
                    $path = Storage::disk('public')->put('review_videos', $files['video']);
                    $review->video_path = '/storage/' . $path;
                }

                $review->update(array_filter([
                    'rating' => $data['rating'] ?? null,
                    'comment' => $data['comment'] ?? null,
                ]));

                $this->updateProductStats($review->product);

                return $review->load('user');
            });
        } catch (Throwable $e) {
            Log::error("Failed to update review $review->id: " . $e->getMessage());

            throw new RuntimeException("Ошибка при обновлении отзыва. Изменения не сохранены.");
        }
    }

    public function deleteReview(Review $review): void
    {
        try {
            DB::transaction(function () use ($review) {
                $this->deleteMedia(array_merge($review->photos ?? [], [$review->video_path]));
                $product = $review->product;
                $review->delete();
                $this->updateProductStats($product);
            });
        } catch (Throwable $e) {
            Log::error("Failed to delete review [ID: $review->id]: " . $e->getMessage());

            throw new RuntimeException("Не удалось удалить отзыв. Попробуйте позже.");
        }
    }

    public function toggleHelpful(Review $review, User $user): array
    {
        $relation = $review->helpfulUsers();
        $exists = $relation->where('user_id', $user->id)->exists();

        if ($exists) {
            $relation->detach($user->id);
            $review->decrement('helpful_count');
        } else {
            $relation->attach($user->id, ['created_at' => now(), 'updated_at' => now()]);
            $review->increment('helpful_count');
        }

        return [
            'is_helpful' => !$exists,
            'helpful_count' => $review->fresh()->helpful_count
        ];
    }

    private function storeTemporaryFiles(array $files): array
    {
        $storedPaths = ['photos' => [], 'video' => null];

        if (!empty($files['photos'])) {
            foreach ($files['photos'] as $photo) {
                $storedPaths['photos'][] = $photo->store('temp/reviews/photos', 'public');
            }
        }

        if (!empty($files['video'])) {
            $storedPaths['video'] = $files['video']->store('temp/reviews/videos', 'public');
        }

        return $storedPaths;
    }

    private function hasPurchased(User $user, Product $product): bool
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where([
                'orders.user_id' => $user->id,
                'order_items.product_id' => $product->id,
                'orders.status' => 'completed'
            ])->exists();
    }

    private function updateProductStats(Product $product): void
    {
        UpdateProductRatingJob::dispatch($product->id);
    }

    private function uploadMedia(array $files, string $folder): array
    {
        return array_map(fn($file) => '/storage/' . $file->store($folder, 'public'), $files);
    }

    private function deleteMedia(?array $paths): void
    {
        if (!$paths) return;
        $cleanPaths = array_map(fn($p) => str_replace('/storage/', '', $p), array_filter($paths));
        Storage::disk('public')->delete($cleanPaths);
    }

    public function getUserReviews(User $user, array $params): LengthAwarePaginator
    {
        $perPage = (int)($params['per_page'] ?? 20);
        $sort = $params['sort'] ?? 'created_at';
        $direction = ($params['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $authUser = Auth::user();

        $query = Review::query()
            ->where('reviews.user_id', $user->id)
            ->where('is_verified_purchase', true)
            ->with(['product:id,title']);

        if ($sort === 'product_name') {
            $query->join('products', 'products.id', '=', 'reviews.product_id')
                ->orderBy('products.title', $direction)
                ->select('reviews.*');
        } elseif ($sort === 'rating') {
            $query->orderBy('reviews.rating', $direction);
        } else {
            $query->orderBy('reviews.created_at', $direction);
        }

        if ($authUser) {
            $query->withExists(['helpfulUsers as is_helpful' => function ($q) use ($authUser) {
                $q->where('user_id', $authUser->id);
            }]);
        }

        return $query->paginate($perPage);
    }
}
