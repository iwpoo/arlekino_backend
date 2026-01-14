<?php

namespace App\Http\Controllers\API\v1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewCommentRequest;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Services\ReviewCommentService;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ReviewCommentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ReviewCommentService $reviewCommentService
    ) {}

    public function index(Review $review): JsonResponse
    {
        $comments = $this->reviewCommentService->getComments($review, auth('sanctum')->user());
        return response()->json($comments);
    }

    public function store(StoreReviewCommentRequest $request, Review $review): JsonResponse
    {
        $comment = $this->reviewCommentService->createComment(
            $review,
            $request->user(),
            $request->validated()
        );

        return response()->json($comment, 201);
    }

    public function destroy(ReviewComment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $this->reviewCommentService->deleteComment($comment);
        return response()->json(null, 204);
    }

    public function toggleLike(ReviewComment $comment): JsonResponse
    {
        try {
            $result = $this->reviewCommentService->toggleCommentLike($comment, auth()->user());
            return response()->json($result);
        } catch (Exception $e) {
            Log::error("Failed to toggle like", [
                'comment_id' => $comment->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Не удалось обработать лайк. Попробуйте позже.'
            ], 500);
        }
    }
}
