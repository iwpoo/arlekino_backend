<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $cartItems = $user->cartItems()
            ->with(['product' => function($query) {
                $query->with(['files']);
            }])
            ->get();

        return response()->json([
            'items' => $cartItems,
            'totalQuantity' => $cartItems->sum('quantity'),
            'totalPrice' => $cartItems->reduce(function ($total, $item) {
                return $total + ($item->product->price * $item->quantity);
            }, 0)
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $user = Auth::user();

        $cartItem = $user->cartItems()
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $request->quantity;
            $cartItem->save();
        } else {
            $cartItem = $user->cartItems()->create([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity
            ]);
        }

        return response()->json($cartItem->load('product'), 201);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        if ($cartItem->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Access denied',
            ], 403);
        }

        $cartItem->update([
            'quantity' => $request->quantity
        ]);

        return response()->json([
            'message' => 'Quantity updated',
            'item' => $cartItem
        ]);
    }

    public function destroy(CartItem $cartItem): JsonResponse
    {
        if ($cartItem->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Access denied',
            ], 403);
        }

        $cartItem->delete();

        return response()->json([
            'message' => 'Item removed from cart'
        ]);
    }
}
