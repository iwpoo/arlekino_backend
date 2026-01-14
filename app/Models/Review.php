<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'rating',
        'comment',
        'photos',
        'video_path',
        'is_verified_purchase',
        'helpful_count',
    ];

    protected $casts = [
        'photos' => 'array',
        'is_verified_purchase' => 'boolean',
        'rating' => 'integer',
        'helpful_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function helpfulUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'review_helpful')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class);
    }
}

