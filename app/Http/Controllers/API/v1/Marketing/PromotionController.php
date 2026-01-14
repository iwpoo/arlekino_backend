<?php

namespace App\Http\Controllers\API\v1\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromotionRequest;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isSeller()) {
            return response()->json(['error' => 'Доступно только для продавцов'], 403);
        }

        $status = $request->query('status');

        $promotions = Promotion::where('user_id', $request->user()->id)
            ->with(['products:id,title,price'])
            ->when($status, fn($q) => $q->byStatus($status))
            ->latest()
            ->paginate($request->query('per_page', 10));

        return response()->json($promotions);
    }

    public function store(PromotionRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $promotion = $request->user()->promotions()->create($request->validated());

                if ($request->has('product_ids')) {
                    $promotion->products()->attach($request->product_ids);
                }

                return response()->json($promotion->load('products:id,title,price'), 201);
            });
        } catch (QueryException $e) {
            Log::error("Database error during promotion creation: " . $e->getMessage());

            return response()->json([
                'message' => 'Ошибка базы данных при создании акции',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);

        } catch (Throwable $e) {
            Log::critical("Unexpected error in PromotionStore: " . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'payload' => $request->validated()
            ]);

            return response()->json([
                'message' => 'Произошла системная ошибка'
            ], 500);
        }
    }

    public function show(Promotion $promotion, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($promotion->user_id !== $user->id) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $promotion->load(['products' => function($query) {
            $query->select('products.id', 'title', 'price');
        }]);

        return response()->json($promotion);
    }

    public function update(PromotionRequest $request, Promotion $promotion): JsonResponse
    {
        if ($promotion->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        try {
            return DB::transaction(function () use ($request, $promotion) {
                $promotion->update($request->validated());

                $promotion->products()->sync($request->product_ids);

                return response()->json($promotion->load('products:id,title,price'));
            });
        } catch (QueryException $e) {
            Log::error("Database error during promotion update [ID: $promotion->id]: " . $e->getMessage());

            return response()->json([
                'message' => 'Ошибка при обновлении данных в базе',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);

        } catch (Throwable $e) {
            Log::critical("Critical error during promotion update [ID: $promotion->id]: " . $e->getMessage(), [
                'payload' => $request->all()
            ]);

            return response()->json([
                'message' => 'Не удалось обновить акцию. Попробуйте позже.'
            ], 500);
        }
    }

    public function destroy(Promotion $promotion, Request $request): JsonResponse
    {
        if ($promotion->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $promotion->delete();

        return response()->json(['message' => 'Акция успешно удалена']);
    }

    public function getUserProducts(Request $request): JsonResponse
    {
        if (!$request->user()->isSeller()) abort(403);

        $products = Product::where('user_id', $request->user()->id)
            ->select(['id', 'title', 'price'])
            ->when($request->search, fn($q) => $q->where('title', 'like', "%$request->search%"))
            ->limit(50)
            ->get();

        return response()->json($products);
    }
}
