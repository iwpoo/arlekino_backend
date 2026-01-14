<?php

namespace App\Http\Controllers\API\v1\Shop;

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

        $cacheKey = "user_{$userId}_purchases_v1_{$page}_" . md5($search);

        $products = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($userId, $search) {
            $perPage = 12;

            $query = Product::query()
                ->with(['files'])
                ->whereIn('id', function ($query) use ($userId) {
                    $query->select('product_id')
                        ->from('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('orders.user_id', $userId)
                        ->where('orders.status', 'completed');
                });

            if (!empty($search)) {
                $query->where('title', 'like', "%$search%");
            }

            return $query->latest()->simplePaginate($perPage);
        });

        return response()->json($products);
    }
}
