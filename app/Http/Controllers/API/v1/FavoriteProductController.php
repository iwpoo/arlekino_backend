<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\FavoriteProduct;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class FavoriteProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $cacheKey = 'user:' . auth()->id() . ':favorites';
        $expiration = now()->addHours(2);

        $favorites = Cache::remember($cacheKey, $expiration, function () {
            return auth()->user()->favoriteProducts()
                ->with(['category', 'files'])
                ->paginate(10);
        });

        return response()->json($favorites);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Product $product): JsonResponse
    {
        auth()->user()->favoriteProducts()->syncWithoutDetaching([$product->id]);

        Cache::forget('user:' . auth()->id() . ':favorites');

        return response()->json([
            'message' => 'Product added to favorites',
            'favorites_count' => Auth::user()->favoriteProducts()->count()
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        Auth::user()->favoriteProducts()->detach($product->id);

        return response()->json([
            'message' => 'The product has been removed from favorites',
            'favorites_count' => Auth::user()->favoriteProducts()->count()
        ]);
    }

    public function check(Product $product): JsonResponse
    {
        $isFavorite = FavoriteProduct::where('user_id', auth()->id())
            ->where('product_id', $product->id)
            ->exists();

        return response()->json(['is_favorite' => $isFavorite]);
    }
}
