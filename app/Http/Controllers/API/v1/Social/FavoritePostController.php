<?php

namespace App\Http\Controllers\API\v1\Social;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\FavoritePostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoritePostController extends Controller
{
    public function __construct(
        protected FavoritePostService $favoritePostService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $posts = $this->favoritePostService->getFavoritePosts(
            $request->user(),
            (int) $request->get('per_page', 10)
        );

        return response()->json($posts);
    }

    public function store(Post $post): JsonResponse
    {
        $status = $this->favoritePostService->addToFavorites(auth()->user(), $post);

        return response()->json([
            'message' => 'Пост добавлен в избранное',
            'is_favorite' => $status
        ], 201);
    }

    public function destroy(Post $post): JsonResponse
    {
        $status = $this->favoritePostService->removeFromFavorites(auth()->user(), $post);

        return response()->json([
            'message' => 'Пост удален из избранного',
            'is_favorite' => $status
        ]);
    }

    public function check(Post $post): JsonResponse
    {
        $isFavorite = $this->favoritePostService->isFavorite(auth()->user(), $post);

        return response()->json(['is_favorite' => $isFavorite]);
    }
}
