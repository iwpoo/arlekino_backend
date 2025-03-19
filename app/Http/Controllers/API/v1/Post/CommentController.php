<?php

namespace App\Http\Controllers\API\v1\Post;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request): JsonResponse
    {
        $postId = $request->route('post');
        $page = $request->input('page', 1);

        $totalCommentsCount = Cache::remember("post_{$postId}_total_comments", now()->addMinutes(10), function () use ($postId) {
            return Comment::where('post_id', $postId)->count();
        });

        $comments = Comment::with(['user', 'children.user'])
            ->where('post_id', $postId)
            ->whereNull('parent_id')
            ->orderByDesc('likes_count')
            ->paginate(10, ['*'], 'page', $page);

        return response()->json([
            'data' => $comments->items(),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
                'total_comments_count' => $totalCommentsCount,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $postId = $request->route('post');
        if (!$postId) {
            return response()->json(['error' => 'Post ID is required'], 400);
        }

        $request->merge(['post_id' => $postId]);

        $validated = $request->validate([
            'content' => 'required|string',
            'post_id' => 'required|exists:posts,id',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'post_id' => $validated['post_id'],
            'parent_id' => $validated['parent_id'],
            'content' => $validated['content'],
        ]);

        $comment->load('user');

        return response()->json($comment, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Comment $comment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $comment->update($validated);

        return response()->json($comment);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
