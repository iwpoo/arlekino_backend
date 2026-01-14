<?php

namespace App\Http\Controllers\API\v1\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Services\CommentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected CommentService $commentService
    ) {}

    public function index(Post $post): JsonResponse
    {
        $comments = $this->commentService->getComments($post);
        return response()->json($comments);
    }

    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $comment = $this->commentService->createComment(
            $post,
            $request->user(),
            $request->validated()
        );

        return response()->json($comment, 201);
    }

    public function show(Comment $comment): JsonResponse
    {
        $data = $this->commentService->getCommentWithBranch($comment);
        return response()->json($data);
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $updated = $this->commentService->updateComment($comment, $request->validated()['content']);
        return response()->json($updated);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $this->commentService->deleteComment($comment);
        return response()->json(null, 204);
    }
}
