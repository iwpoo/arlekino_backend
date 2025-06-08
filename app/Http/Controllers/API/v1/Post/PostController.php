<?php

namespace App\Http\Controllers\API\v1\Post;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $type = $request->query('type', 'subscriptions');
        $perPage = $request->query('per_page', 10);

        if ($type === 'subscriptions') {
            $followingIds = Follow::where('follower_id', $userId)
                ->pluck('following_id');

            $posts = Post::whereIn('posts.user_id', $followingIds)
            ->where('is_published', true)
                ->with(['user', 'files', 'comments'])
                ->leftJoin('likes as user_likes', function ($join) use ($userId) {
                    $join->on('posts.id', '=', 'user_likes.post_id')
                        ->where('user_likes.user_id', $userId);
                })
                ->select([
                    'posts.*',
                    DB::raw('IF(user_likes.id IS NULL, 0, 1) as is_liked')
                ])
                ->orderByDesc('posts.created_at')
                ->paginate($perPage);
        } elseif ($type === 'recommendations') {
            $posts = Post::with(['user', 'files', 'comments'])
                ->leftJoin('likes as user_likes', function ($join) use ($userId) {
                    $join->on('posts.id', '=', 'user_likes.post_id')
                        ->where('user_likes.user_id', $userId);
                })
                ->select([
                    'posts.*',
                    DB::raw('CASE WHEN user_likes.id IS NULL THEN 0 ELSE 1 END as is_liked')
                ])
                ->orderByDesc('likes_count')
                ->paginate($perPage);
        } else {
            return response()->json(['error' => 'Invalid type parameter'], 400);
        }

        return response()->json($posts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'files' => 'required|array',
                'files.*' => 'file|mimes:jpeg,png,jpg,gif,mp4',
                'content' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        }

        $post = $request->user()->posts()->create([
            'content' => $validated['content'] ?? null,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('post_files', 'public');
                $post->files()->create([
                    'file_path' => $path,
                    'file_type' => strtok($file->getClientMimeType(), '/'),
                ]);
            }
        }

        return response()->json($post, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post): JsonResponse
    {
        $this->incrementViewsCount($post, auth()->id());

        $post->load(['user', 'files', 'comments']);

        $post->is_liked = $post->isLikedByUser($post->id, auth()->id());

        return response()->json($post);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        if ($post->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'nullable|string',
        ]);

        $post->update($validated);

        return response()->json($post);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        if ($post->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }

    public function incrementViews(Post $post): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Просмотр доступен только для авторизованных пользователей'], 403);
        }

        $userId = auth()->id();

        $this->incrementViewsCount($post, $userId);

        return response()->json(['message' => 'Просмотр засчитан']);
    }

    private function incrementViewsCount(Post $post, int $userId): void
    {
        $cacheKey = "post:$post->id:user:$userId";

        if (!Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addHours(24));

            $post->increment('views_count');
        }
    }
}
