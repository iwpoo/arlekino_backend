<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'views_count',
        'shares_count',
        'likes_count',
        'comments_count',
        'is_published',
        'user_id',
        'product_id',
        'is_review_post',
        'file_path',
        'file_type',
    ];

    protected $casts = [
        'is_review_post' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(PostFile::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function favoritePosts(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_posts');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PostReport::class);
    }

    public function isLikedByUser($postId, $userId): mixed
    {
        return Cache::remember("post:$postId:user:$userId:liked", 60, function () use ($postId, $userId) {
            return Like::where('user_id', $userId)->where('post_id', $postId)->exists();
        });
    }

    public function incrementSharesCount(): void
    {
        $this->increment('shares_count');
    }
}
