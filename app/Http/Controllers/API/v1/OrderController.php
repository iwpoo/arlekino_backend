<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Order::where('user_id', auth()->id())
                ->with('user')
                ->paginate(10)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'shipping_address' => 'required|string',
                'payment_method' => 'required|string',
            ]);

            $totalAmount = $this->calculateTotal($validated['products']);

            DB::transaction(function () use ($validated, $totalAmount) {
                Order::create([
                    'user_id' => auth()->id(),
                    'shipping_address' => $validated['shipping_address'],
                    'payment_method' => $validated['payment_method'],
                    'total_amount' => $totalAmount,
                ]);
            });

            return response()->json(['message' => 'Order created successfully'], 201);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order): JsonResponse
    {
        abort_if($order->user_id !== auth()->id(), 403);

        return response()->json($order->load('user', 'items.product.files'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,processing,completed,canceled',
            'payment_method' => 'sometimes|string',
            'shipping_address' => 'sometimes|string'
        ]);

        $order->update($validated);
        return response()->json($order);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order): JsonResponse
    {
        abort_if($order->user_id !== auth()->id(), 403);

        $order->delete();
        return response()->json(null, 204);
    }

    private function calculateTotal(array $products): float
    {
        return array_sum(array_map(fn($product) => $product['quantity'] * $product['price'], $products));
    }
}
