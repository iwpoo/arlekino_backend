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
    public function toggleLike(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "post:$post->id:user:$user->id:liked";

        $like = Like::where('user_id', $user->id)->where('post_id', $post->id)->first();

        if ($like) {
            $like->delete();
            $post->decrement('likes_count');
            Cache::forget($cacheKey);
            return response()->json(['liked' => false, 'likes' => $post->likes_count, 'postId' => $post->id]);
        }

        Like::create(['user_id' => $user->id, 'post_id' => $post->id]);
        $post->increment('likes_count');
        Cache::forget($cacheKey);
        return response()->json(['liked' => true, 'likes' => $post->likes_count, 'postId' => $post->id]);
    }
}
