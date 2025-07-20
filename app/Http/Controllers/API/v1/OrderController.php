<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use chillerlan\QRCode\QRCode;
use Throwable;

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
        } catch (Throwable $e) {
            return response()->json($e->errors(), 500);
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

    public function generateQR(Order $order): JsonResponse
    {
        $url = route('order.status.update', ['order' => $order]);

        $qrcode = new QRCode();
        $imageData = $qrcode->render($url);

        return response()->json(['qrcode' => $imageData]);
    }

    public function updateStatus(Order $order, Request $request): JsonResponse
    {
        RateLimiter::hit($key = $request->bearerToken() ?: 'guest');

        if (RateLimiter::tooManyAttempts($key, 10)) {
            Log::info('Too many attempts', ['key' => $key]);
            return response()->json(['error' => 'Слишком много попыток'], 429);
        }

        $token = $request->bearerToken();
        if (!$token) {
            Log::info('Token not provided');
            return response()->json(['error' => 'Токен не найден'], 401);
        }

        $isCourier = $order->courier->api_token == $token;
        if ($isCourier && !$order->status === 'processing') {
            Log::info('Courier not provided');
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $request->validate([
            'status' => 'required|string|in:pending,processing,assembling,shipped,completed,canceled'
        ]);

        Log::info("UPDATE PROCESSING STATUS");

        $order->update([
            'status' => $request->status,
        ]);

        Log::info("Курьер {$order->courier->id} обновил заказ $order->id на статус 'sent'");

        return response()->json($order);
    }

    private function calculateTotal(array $products): float
    {
        return array_sum(array_map(fn($product) => $product['quantity'] * $product['price'], $products));
    }
}
