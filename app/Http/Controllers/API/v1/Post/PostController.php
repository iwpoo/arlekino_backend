<?php

namespace App\Http\Controllers\API\v1\Post;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

            $posts = Post::whereIn('user_id', $followingIds)
                ->where('is_published', true)
                ->with(['user', 'comments', 'files'])
                ->latest()
                ->paginate($perPage);
        } elseif ($type === 'recommendations') {
            $followingIds = Follow::where('follower_id', $userId)
                ->pluck('following_id');

            $posts = Post::whereNotIn('user_id', $followingIds)
                ->where('is_published', true)
                ->with(['user', 'comments', 'files'])
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
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpeg,png,jpg,gif,mp4|max:20480',
        ]);

        $post = $request->user()->posts()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
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
        $post->increment('views_count');

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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
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
}
