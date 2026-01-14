<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Jobs\ProcessProductFilesJob;
use App\Jobs\RecordInteractionJob;
use App\Models\Follow;
use App\Models\Product;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ProductService
{
    public function __construct(
        protected CurrencyConverter $currencyConverter
    ) {}

    public function getProducts(User $user, array $params): LengthAwarePaginator
    {
        $type = $params['type'] ?? 'subscriptions';
        $perPage = (int)($params['per_page'] ?? 10);

        $blockedIds = $this->getBlockedIds($user->id);
        $preferredCurrency = $user->currency ?? $this->currencyConverter->getBaseCurrency();

        $query = Product::with(['user', 'files', 'promotions'])
            ->whereNotIn('user_id', $blockedIds);

        $query = match ($type) {
            'subscriptions' => $this->applySubscriptionsFilter($query, $user),
            'followers_activity' => $this->applyActivityFilter($query, $user),
            'my_content' => $query->where('user_id', $user->id),
            'recommendations' => $query->orderByDesc('likes_count'),
            default => throw new InvalidArgumentException('Invalid type parameter', 400),
        };

        if ($user->isSeller() && in_array($type, ['subscriptions', 'recommendations'])) {
            $query->whereHas('user', fn($q) => $q->where('role', UserRole::SELLER->value));
        }

        $products = $query->latest()->paginate($perPage);

        $products->getCollection()->transform(fn($p) => $this->enrichProduct($p, $preferredCurrency));

        return $products;
    }

    public function createProduct(User $user, array $data, ?array $files = []): Product
    {
        try {
            return DB::transaction(function () use ($user, $data, $files) {
                $baseCurrency = $this->currencyConverter->getBaseCurrency();
                $inputCurrency = strtoupper($data['price_currency'] ?? $user->currency ?? $baseCurrency);

                $priceInBase = $this->currencyConverter->convert((float)$data['price'], $inputCurrency, $baseCurrency);

                $product = $user->products()->create(array_merge($data, [
                    'price' => (int)round($priceInBase)
                ]));

                if (!empty($files)) {
                    $fileData = [];
                    foreach ($files as $file) {
                        $fileData[] = [
                            'path' => $file->store('product_files/temp', 'public'),
                            'mime' => $file->getClientMimeType()
                        ];
                    }

                    DB::afterCommit(function () use ($product, $fileData) {
                        ProcessProductFilesJob::dispatch($product->id, $fileData);
                    });
                }

                return $product->load(['user', 'files']);
            });
        } catch (Throwable $e) {
            Log::error("Product creation failed: " . $e->getMessage(), [
                'user_id' => $user->id,
                'data' => $data
            ]);

            throw new RuntimeException("Не удалось создать товар. Ошибка сервера.");
        }
    }

    public function getProduct(Product $product, User $user): Product
    {
        $blockedIds = $this->getBlockedIds($user->id);
        if (in_array($product->user_id, $blockedIds)) {
            throw new RuntimeException('Product not found', 404);
        }

        $product->load(['user', 'files']);

        RecordInteractionJob::dispatch($user->id, $product->id, 'product', 'view', 1);

        $currency = $user->currency ?? $this->currencyConverter->getBaseCurrency();

        return $this->enrichProduct($product, $currency);
    }

    public function updateProduct(Product $product, array $data, ?array $files = [], ?string $inputCurrency = null): Product
    {
        try {
            return DB::transaction(function () use ($product, $data, $files, $inputCurrency) {
                $baseCurrency = $this->currencyConverter->getBaseCurrency();
                $currency = strtoupper($inputCurrency ?? $baseCurrency);

                $priceInBase = $this->currencyConverter->convert((float)$data['price'], $currency, $baseCurrency);
                $data['price'] = (int)round($priceInBase);

                $product->update($data);

                if (isset($data['existing_media_ids'])) {
                    $ids = is_array($data['existing_media_ids']) ? $data['existing_media_ids'] : json_decode($data['existing_media_ids'], true);
                    $product->files()->whereNotIn('id', $ids)->delete();
                } else {
                    $product->files()->delete();
                }

                if (!empty($files)) {
                    $fileData = [];
                    foreach ($files as $file) {
                        $fileData[] = [
                            'path' => $file->store('product_files/temp', 'public'),
                            'mime' => $file->getClientMimeType()
                        ];
                    }

                    DB::afterCommit(function () use ($product, $fileData) {
                        ProcessProductFilesJob::dispatch($product->id, $fileData);
                    });
                }

                return $product->load('files');
            });
        } catch (Throwable $e) {
            Log::error("Product update failed [ID: $product->id]: " . $e->getMessage());
            throw new RuntimeException("Ошибка при обновлении товара");
        }
    }

    public function deleteProduct(Product $product): ?bool
    {
        return $product->delete();
    }

    private function enrichProduct(Product $product, string $currency): Product
    {
        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        $product->best_promotion = $product->getBestPromotion();
        $product->original_price = $product->price;
        $product->currency = $currency;

        $product->converted_price = ($currency === $baseCurrency)
            ? $product->price
            : $this->currencyConverter->convert((float)$product->price, $baseCurrency, $currency);

        return $product;
    }

    private function getBlockedIds(int $userId): array
    {
        return Cache::remember("user_blocks_$userId", 3600, fn() =>
            UserBlock::where('blocker_id', $userId)->pluck('blocked_id')->toArray()
        );
    }

    private function applySubscriptionsFilter($query, User $user): mixed
    {
        $followingIds = Cache::remember("user_following_$user->id", 600, fn() =>
            Follow::where('follower_id', $user->id)->pluck('following_id')->toArray()
        );
        return $query->whereIn('user_id', $followingIds);
    }

    private function applyActivityFilter($query, User $user): mixed
    {
        $ids = Follow::where('following_id', $user->id)->pluck('follower_id')
            ->merge(Follow::where('follower_id', $user->id)->pluck('following_id'))
            ->unique();
        return $query->whereIn('user_id', $ids)->where('user_id', '!=', $user->id);
    }
}
