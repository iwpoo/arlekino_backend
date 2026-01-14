<?php

namespace App\Http\Controllers\API\v1\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class FavoriteProductController extends Controller
{
    public function index(): JsonResponse
    {
        return auth()->user()->favoriteProducts()
            ->with(['category', 'files'])
            ->latest('created_at')
            ->paginate(10);
    }

    public function store(Product $product): JsonResponse
    {
        $user = Auth::user();

        $changes = $user->favoriteProducts()->syncWithoutDetaching([$product->id]);

        Cache::forget("user:$user->id:fav_check:$product->id");

        return response()->json([
            'message' => 'Product added to favorites',
            'is_new' => count($changes['attached']) > 0
        ], 201);
    }

    public function destroy(Product $product): JsonResponse
    {
        $user = Auth::user();
        $user->favoriteProducts()->detach($product->id);

        return response()->json([
            'message' => 'Removed from favorites'
        ]);
    }

    public function check(Product $product): JsonResponse
    {
        $userId = Auth::id();

        $isFavorite = Cache::remember("user:$userId:fav_check:$product->id", 600, function () use ($userId, $product) {
            return Auth::user()->favoriteProducts()->where('product_id', $product->id)->exists();
        });

        return response()->json(['is_favorite' => $isFavorite]);
    }
}
