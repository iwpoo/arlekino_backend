<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $search = $request->get('search', '');
        $page = $request->get('page', 1);
        $perPage = 12;

        $cacheKey = "user_{$userId}_completed_products_page_{$page}_search_" . md5($search);

        $products = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($userId, $search, $perPage) {
            $query = Product::whereHas('orderItems.order', function ($q) use ($userId) {
                $q->where('user_id', $userId)->where('status', 'completed');
            });

            if (!empty($search)) {
                $query->where('title', 'like', "%{$search}%");
            }

            return $query->with('files')->paginate($perPage);
        });

        return response()->json($products);
    }
}
