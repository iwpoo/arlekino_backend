<?php

namespace App\Services;

use App\Events\SocialActivity;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class LikeService
{
    public function __construct(
        protected RecommendationService $recommendationService
    ) {}

    public function like(Post $post, User $user): array
    {
        if ($post->likes()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['post' => 'Вы уже поставили лайк этому посту.']);
        }

        try {
            return DB::transaction(function () use ($post, $user) {
                Like::create([
                    'user_id' => $user->id,
                    'post_id' => $post->id
                ]);

                $post->increment('likes_count');
                $currentLikes = $post->likes_count;

                $this->recommendationService->recordInteraction($user, $post, 'like', 3);

                if ($post->user_id !== $user->id) {
                    event(new SocialActivity('like', $post, $post->user, $user));
                }

                $this->clearLikeCache($post->id, $user->id);

                return [
                    'liked' => true,
                    'likes' => $currentLikes,
                    'postId' => $post->id
                ];
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("Ошибка при постановке лайка: " . $e->getMessage(), [
                'user_id' => $user->id,
                'post_id' => $post->id
            ]);
            throw new RuntimeException("Не удалось поставить лайк. Попробуйте позже.");
        }
    }

    public function unlike(Post $post, User $user): array
    {
        $like = $post->likes()->where('user_id', $user->id)->first();

        if (!$like) {
            throw ValidationException::withMessages(['post' => 'Лайк еще не поставлен.']);
        }

        try {
            return DB::transaction(function () use ($post, $user, $like) {
                $like->delete();
                $post->decrement('likes_count');
                $currentLikes = $post->likes_count;

                $this->clearLikeCache($post->id, $user->id);

                return [
                    'liked' => false,
                    'likes' => $currentLikes,
                    'postId' => $post->id
                ];
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("Ошибка при удалении лайка: " . $e->getMessage(), [
                'user_id' => $user->id,
                'post_id' => $post->id
            ]);
            throw new RuntimeException("Не удалось убрать лайк");
        }
    }

    private function clearLikeCache(int $postId, int $userId): void
    {
        Cache::forget("post:$postId:user:$userId:liked");
    }
}
