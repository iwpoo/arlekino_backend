<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'views_count',
        'shares_count',
        'is_published',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function isLikedByUser($postId, $userId): mixed
    {
        return Cache::remember("post:$postId:user:$userId:liked", 60, function () use ($postId, $userId) {
            return Like::where('user_id', $userId)->where('post_id', $postId)->exists();
        });
    }
}
