<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FavoritePostService
{
    public function getFavoritePosts(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return $user->favoritePosts()
            ->with(['user', 'files'])
            ->withExists(['likes as is_liked' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->latest('posts.created_at')
            ->paginate($perPage);
    }

    public function addToFavorites(User $user, Post $post): bool
    {
        try {
            return DB::transaction(function () use ($user, $post) {
                if ($user->favoritePosts()->where('post_id', $post->id)->exists()) {
                    return true;
                }

                $user->favoritePosts()->attach($post->id);
                $this->clearFavoriteCache($user, $post);

                return true;
            });
        } catch (Throwable $e) {
            Log::error("Error adding post to favorites", [
                'user_id' => $user->id,
                'post_id' => $post->id,
                'error'   => $e->getMessage()
            ]);
            throw new RuntimeException("Не удалось добавить в избранное");
        }
    }

    public function removeFromFavorites(User $user, Post $post): bool
    {
        try {
            return DB::transaction(function () use ($user, $post) {
                $user->favoritePosts()->detach($post->id);
                $this->clearFavoriteCache($user, $post);

                return false;
            });
        } catch (Throwable $e) {
            Log::error("Error removing post from favorites", [
                'user_id' => $user->id,
                'post_id' => $post->id,
                'error'   => $e->getMessage()
            ]);
            throw new RuntimeException("Не удалось удалить из избранного");
        }
    }

    public function isFavorite(User $user, Post $post): bool
    {
        $cacheKey = "user:$user->id:post:$post->id:is_favorite";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($user, $post) {
            return $user->favoritePosts()->where('post_id', $post->id)->exists();
        });
    }

    private function clearFavoriteCache(User $user, Post $post): void
    {
        Cache::forget("user:$user->id:post:$post->id:is_favorite");
    }
}
