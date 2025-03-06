<?php

namespace App\Http\Controllers\API\v1\Post;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LikeController extends Controller
{
    public function like(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if (Like::where('user_id', $user->id)->where('post_id', $post->id)->exists()) {
            return response()->json(['message' => 'Already liked'], 409);
        }

        Like::create(['user_id' => $user->id, 'post_id' => $post->id]);
        $post->increment('likes_count');

        $this->clearLikeCache($post->id, $user->id);

        return response()->json([
            'liked' => true,
            'likes' => $post->likes_count,
            'postId' => $post->id
        ]);
    }

    public function unlike(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();
        $like = Like::where('user_id', $user->id)->where('post_id', $post->id)->first();

        if (!$like) {
            return response()->json(['message' => 'Not liked yet'], 409);
        }

        $like->delete();
        $post->decrement('likes_count');

        $this->clearLikeCache($post->id, $user->id);

        return response()->json([
            'liked' => false,
            'likes' => $post->likes_count,
            'postId' => $post->id
        ]);
    }

    private function clearLikeCache(int $postId, int $userId): void
    {
        Cache::forget("post:$postId:user:$userId:liked");
    }
}
