<?php

namespace App\Http\Controllers\API\v1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartItemAddRequest;
use App\Http\Requests\CartItemUpdateRequest;
use App\Models\CartItem;
use App\Services\CartService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function index(): JsonResponse
    {
        $data = $this->cartService->getCartItems(auth()->user());
        return response()->json($data);
    }

    public function store(CartItemAddRequest $request): JsonResponse
    {
        try {
            $item = $this->cartService->addItem($request->user(), $request->validated());

            return response()->json($item, 201);
        } catch (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);

        } catch (Throwable $e) {
            Log::error("Cart Addition Error: " . $e->getMessage(), [
                'user_id' => auth()->id(),
                'payload' => $request->validated()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Не удалось добавить товар в корзину. Попробуйте позже.'
            ], 500);
        }
    }

    public function update(CartItemUpdateRequest $request, CartItem $cartItem): JsonResponse
    {
        Gate::authorize('update', $cartItem);

        $item = $this->cartService->updateQuantity($cartItem, $request->validated()['quantity']);

        return response()->json([
            'success' => true,
            'item' => $item
        ]);
    }

    public function destroy(CartItem $cartItem): JsonResponse
    {
        Gate::authorize('delete', $cartItem);

        $this->cartService->removeItem($cartItem);

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart'
        ]);
    }
}
