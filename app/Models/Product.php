<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\ItemCondition;
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
        'variants',
        'similarProducts'
    ];

    protected $casts = [
        'condition' => ItemCondition::class,
        'discountType' => DiscountType::class,
        'attributes' => 'array',
    ];

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

    public function withVariants(): self
    {
        $cacheKey = "product_variants_{$this->id}";
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
                        $query->orWhere('title', 'like', "%{$keyword}%");
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
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getFirstDeliveryPointAttribute(): mixed
    {
        $points = $this->delivery_points;
        return !empty($points) ? $points[0] : null;
    }
}
