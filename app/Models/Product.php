<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\ItemCondition;
use App\Jobs\SyncEntityToElasticsearch;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'attributes',
        'title',
        'content',
        'price',
        'discountType',
        'discountValue',
        'quantity',
        'condition',
        'refund',
        'inStock',
        'points',
        'views_count',
        'shares_count',
        'likes_count',
        'reviews_count',
        'questions_count',
        'rating',
        'variants',
        'similarProducts',
        'question'
    ];

    protected $appends = ['best_promotion'];

    protected $casts = [
        'condition' => ItemCondition::class,
        'discountType' => DiscountType::class,
        'attributes' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn ($model) => SyncEntityToElasticsearch::dispatch($model));
        static::deleted(fn ($model) => SyncEntityToElasticsearch::dispatch($model, true));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_products');
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function withVariants(): self
    {
        $cacheKey = "product_variants_$this->id";
        $cacheDuration = now()->addHours();

        $this->variants = Cache::remember($cacheKey, $cacheDuration, function() {
            return self::where('user_id', $this->user_id)
                ->where('id', '!=', $this->id)
                ->where('category_id', $this->category_id)
                ->where(function($query) {
                    $normalizedTitle = mb_strtolower(preg_replace('/\s+/', '', $this->title));

                    $query->whereRaw("LOWER(REGEXP_REPLACE(title, '[[:space:]]', '')) = ?", [$normalizedTitle])
                        ->orWhere('title', 'like', substr($this->title, 0, 15).'%');
                })
                ->orderBy('price')
                ->limit(15)
                ->get();
        });

        return $this;
    }

    public function similarProducts(int $perPage = 8): self
    {
        $cacheKey = "similar_products_{$this->id}_page_" . request()->get('page', 1);
        $cacheDuration = now()->addHours(12);

        $this->similarProducts = Cache::remember($cacheKey, $cacheDuration, function() use ($perPage) {
            return self::where('category_id', $this->category_id)
                ->where('id', '!=', $this->id)
                ->where(function($query) {
                    $keywords = explode(' ', $this->title);
                    foreach (array_slice($keywords, 0, 3) as $keyword) {
                        $query->orWhere('title', 'like', "%$keyword%");
                    }
                })
                ->orderByRaw("
                CASE
                    WHEN title LIKE ? THEN 0
                    WHEN title LIKE ? THEN 1
                    ELSE 2
                END",
                    [$this->title . '%', '%' . $this->title . '%']
                )
                ->orderBy('views_count', 'desc')
                ->with(['files'])
                ->paginate($perPage);
        });

        return $this;
    }

    public function scopeWithIsFavorite($query, $userId = null)
    {
        if (!$userId && auth()->check()) {
            $userId = auth()->id();
        }

        if ($userId) {
            $query->addSelect([
                'is_favorite' => FavoriteProduct::selectRaw('1')
                    ->whereColumn('product_id', 'products.id')
                    ->where('user_id', $userId)
                    ->limit(1)
            ]);
        }

        return $query;
    }

    public function getDeliveryPointsAttribute(): array
    {
        if (empty($this->points)) {
            return [];
        }

        try {
            $points = json_decode($this->points, true);
            return is_array($points) ? $points : [];
        } catch (Exception) {
            return [];
        }
    }

    public function getFirstDeliveryPointAttribute(): mixed
    {
        $points = $this->delivery_points;
        return !empty($points) ? $points[0] : null;
    }

    public function getBestPromotion(): ?Promotion
    {
        $activePromotions = $this->promotions()
            ->active()
            ->get();

        if ($activePromotions->isEmpty()) {
            return null;
        }

        if ($activePromotions->count() === 1) {
            return $activePromotions->first();
        }

        return $activePromotions->reduce(function (?Promotion $carry, Promotion $promotion) {
            if ($carry === null) {
                return $promotion;
            }

            $carryDiscount = $carry->calculateDiscountedPrice($this->price);
            $currentDiscount = $promotion->calculateDiscountedPrice($this->price);

            if ($currentDiscount < $carryDiscount) {
                return $promotion;
            }

            if ($currentDiscount === $carryDiscount && $promotion->created_at > $carry->created_at) {
                return $promotion;
            }

            return $carry;
        });
    }

    public function getFinalPrice(): float
    {
        $bestPromotion = $this->getBestPromotion();

        if ($bestPromotion) {
            return $bestPromotion->calculateDiscountedPrice($this->price);
        }

        return $this->price;
    }

    public function getBestPromotionAttribute(): ?Promotion
    {
        return $this->getBestPromotion();
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'popularity' => $this->likes_count + $this->views_count,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'is_published' => (bool)$this->is_published,
        ];
    }
}
