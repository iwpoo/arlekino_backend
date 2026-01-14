<?php

namespace App\Http\Controllers\API\v1\Social;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\LikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function __construct(
        protected LikeService $likeService
    ) {}

    public function like(Request $request, Post $post): JsonResponse
    {
        return response()->json($this->likeService->like($post, $request->user()), 201);
    }

    public function unlike(Request $request, Post $post): JsonResponse
    {
        return response()->json($this->likeService->unlike($post, $request->user()));
    }
}
