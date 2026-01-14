<?php

namespace App\Services;

use App\Jobs\ProcessPostFilesJob;
use App\Jobs\UpdatePostCounterJob;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PostService
{
    public function getPosts(User $user, array $params): LengthAwarePaginator
    {
        $type = $params['type'] ?? 'subscriptions';
        $perPage = (int)($params['per_page'] ?? 10);

        $blockedIds = $this->getCachedIds($user, 'blocked');

        $query = Post::with(['user', 'files'])
            ->whereNotIn('user_id', $blockedIds);

        if ($type === 'subscriptions') {
            $followingIds = $this->getCachedIds($user, 'following');
            $query->whereIn('user_id', $followingIds);

            if ($user->isSeller()) {
                $query->whereHas('user', fn($q) => $q->where('role', 'seller'));
            }
        }
        elseif ($type === 'followers_activity') {
            $userIds = array_unique(array_merge(
                $this->getCachedIds($user, 'following'),
                $this->getCachedIds($user, 'followers')
            ));
            $query->whereIn('user_id', $userIds)->where('user_id', '!=', $user->id);
        }
        elseif ($type === 'user_posts') {
            $query->where('user_id', $user->id);
        }
        elseif ($type === 'recommendations') {
            $query->orderByDesc('likes_count');
            if ($user->isSeller()) {
                $query->whereHas('user', fn($q) => $q->where('role', 'seller'));
            }
        }

        return $query->latest()->paginate($perPage);
    }

    public function createPost(User $user, array $data, ?array $files = []): Post
    {
        try {
            return DB::transaction(function () use ($user, $data, $files) {
                $post = $user->posts()->create([
                    'content' => $data['content'] ?? null,
                ]);

                if (!empty($files)) {
                    $fileData = [];
                    foreach ($files as $file) {
                        $fileData[] = [
                            'path' => $file->store('post_files/temp', 'public'),
                            'mime' => $file->getClientMimeType(),
                            'original_name' => $file->getClientOriginalName()
                        ];
                    }

                    DB::afterCommit(function () use ($post, $fileData) {
                        ProcessPostFilesJob::dispatch($post->id, $fileData);
                    });
                }

                return $post->load('files');
            });
        } catch (Throwable $e) {
            Log::error("Failed to create post: " . $e->getMessage(), [
                'user_id' => $user->id,
                'files_count' => count($files)
            ]);

            throw new RuntimeException("Не удалось опубликовать пост. Попробуйте еще раз.");
        }
    }

    public function incrementViews(Post $post, int $userId): void
    {
        $key = "post_viewed:$post->id:user:$userId";

        if (!Cache::has($key)) {
            Cache::put($key, true, now()->addHours(24));
            UpdatePostCounterJob::dispatch($post->id, 'views_count');
        }
    }

    public function report(Post $post, User $user, string $reason): void
    {
        $exists = PostReport::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->exists();

        if ($exists) {
            throw new RuntimeException('Post already reported');
        }

        PostReport::create([
            'post_id' => $post->id,
            'reporter_id' => $user->id,
            'reason' => $reason,
        ]);
    }

    private function getCachedIds(User $user, string $relation): array
    {
        return Cache::remember("user_{$user->id}_$relation", 3600, function () use ($user, $relation) {
            return match($relation) {
                'blocked' => $user->blockedUsers()->pluck('blocked_id')->toArray(),
                'following' => $user->followings()->pluck('following_id')->toArray(),
                'followers' => $user->followers()->pluck('follower_id')->toArray(),
            };
        });
    }
}
