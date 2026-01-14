<?php

namespace App\Services;

use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ReviewCommentService
{
    public function getComments(Review $review, ?User $user): LengthAwarePaginator
    {
        $query = $review->comments()
            ->with(['user', 'children.user'])
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc');

        if ($user) {
            $query->addSelect([
                'is_liked' => DB::table('review_comment_likes')
                    ->whereColumn('comment_id', 'review_comments.id')
                    ->where('user_id', $user->id)
                    ->selectRaw('1')
                    ->limit(1)
            ]);
        }

        return $query->paginate(20);
    }

    public function createComment(Review $review, User $user, array $data): ReviewComment
    {
        return ReviewComment::create([
            'review_id' => $review->id,
            'user_id'   => $user->id,
            'parent_id' => $data['parent_id'] ?? null,
            'content'   => $data['content'],
        ])->load('user');
    }

    public function deleteComment(ReviewComment $comment): void
    {
        $comment->delete();
    }

    public function toggleCommentLike(ReviewComment $comment, User $user): array
    {
        try {
            return DB::transaction(function () use ($comment, $user) {
                $likeQuery = DB::table('review_comment_likes')
                    ->where('comment_id', $comment->id)
                    ->where('user_id', $user->id);

                if ($likeQuery->exists()) {
                    $likeQuery->delete();
                    $comment->decrement('likes_count');
                    $isLiked = false;
                } else {
                    DB::table('review_comment_likes')->insert([
                        'comment_id' => $comment->id,
                        'user_id'    => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $comment->increment('likes_count');
                    $isLiked = true;
                }

                return [
                    'is_liked'    => $isLiked,
                    'likes_count' => $comment->fresh()->likes_count,
                ];
            });
        } catch (Throwable $e) {
            Log::error("Toggle Like Error [Comment: $comment->id, User: $user->id]: " . $e->getMessage());

            throw new RuntimeException("Не удалось изменить состояние лайка. Попробуйте позже.");
        }
    }
}
