<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CommentService
{
    public function getComments(Post $post, int $perPage = 10): array
    {
        $cacheKey = "post_{$post->id}_total_comments";

        $totalCount = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($post) {
            return $post->comments()->count();
        });

        $paginator = Comment::query()
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->with(['user', 'children.user'])
            ->orderByDesc('likes_count')
            ->paginate($perPage);

        $data = $paginator->toArray();
        $data['total_comments_count'] = $totalCount;
        return $data;
    }

    public function createComment(Post $post, User $user, array $data): Comment
    {
        try {
            return DB::transaction(function () use ($post, $user, $data) {
                $comment = Comment::create([
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                    'parent_id' => $data['parent_id'] ?? null,
                    'content' => $data['content'],
                ]);

                Cache::forget("post_{$post->id}_total_comments");

                return $comment->load('user');
            });
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("Ошибка создания комментария в посте $post->id: " . $e->getMessage(), [
                'user_id' => $user->id,
                'data'    => $data
            ]);

            throw new RuntimeException("Не удалось отправить комментарий. Попробуйте позже.");
        }
    }

    public function getCommentWithBranch(Comment $comment): array
    {
        $comment->load(['user', 'children.user', 'parent.user']);

        $root = $comment->parent_id ? $this->findRoot($comment) : $comment;

        return [
            'comment' => $comment,
            'root'    => $root->loadMissing(['user', 'children.user'])
        ];
    }

    public function updateComment(Comment $comment, string $content): Comment
    {
        $comment->update(['content' => $content]);
        return $comment;
    }

    public function deleteComment(Comment $comment): void
    {
        $postId = $comment->post_id;
        $comment->delete();
        Cache::forget("post_{$postId}_total_comments");
    }

    private function findRoot(Comment $comment): Comment
    {
        if (!$comment->parent_id) return $comment;

        $parent = $comment->parent()->first();
        return $parent ? $this->findRoot($parent) : $comment;
    }
}
