<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\ItemCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'condition' => ItemCondition::class,
        'discountType' => DiscountType::class,
        'attributes' => 'array'
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
}
