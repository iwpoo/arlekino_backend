<?php

namespace App\Http\Controllers\API\v1\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\PostCreateRequest;
use App\Http\Requests\PostReportRequest;
use App\Http\Requests\PostUpdateRequest;
use App\Models\Post;
use App\Services\PostService;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class PostController extends Controller
{
    public function __construct(
        protected PostService $postService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $posts = $this->postService->getPosts($request->user(), $request->all());
        return response()->json($posts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PostCreateRequest $request): JsonResponse
    {
        $post = $this->postService->createPost(
            $request->user(),
            $request->validated(),
            $request->file('files')
        );
        return response()->json($post, 201);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json($post->load(['user', 'files']));
    }

    public function update(PostUpdateRequest $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);
        $post->update($request->validated());
        return response()->json($post);
    }

    public function destroy(Post $post): JsonResponse
    {
        Gate::authorize('delete', $post);
        $post->delete();
        return response()->json(['message' => 'Post deleted successfully']);
    }

    public function incrementViews(Post $post): JsonResponse
    {
        $this->postService->incrementViews($post, auth()->id());
        return response()->json(['message' => 'View counted']);
    }

    public function report(PostReportRequest $request, Post $post): JsonResponse
    {
        try {
            $this->postService->report($post, $request->user(), $request->reason);
            return response()->json(['message' => 'Post reported successfully'], 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode());
        }
    }

    public function incrementShares(Post $post, RecommendationService $recommendationService): JsonResponse
    {
        $post->increment('shares_count');
        $recommendationService->recordInteraction(auth()->user(), $post, 'share', 3);
        return response()->json(['message' => 'Share counted']);
    }
}
