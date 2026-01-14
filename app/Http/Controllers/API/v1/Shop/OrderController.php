<?php

namespace App\Http\Controllers\API\v1\Shop;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderCreationRequest;
use App\Http\Requests\OrderStatusUpdateRequest;
use App\Http\Requests\OrderUpdateRequest;
use App\Http\Requests\PrecalculateRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->orderService->getOrders($request->user(), (int)$request->get('per_page', 10));
        return response()->json($result['data']);
    }

    public function precalculate(PrecalculateRequest $request): JsonResponse
    {
        $result = $this->orderService->precalculate(
            $request->user(),
            $request->validated()['item_ids'],
            $request->input('currency')
        );

        return response()->json(['total' => $result]);
    }

    public function store(OrderCreationRequest $request): JsonResponse
    {
        Gate::authorize('store', Order::class);
        $result = $this->orderService->processOrderCreation($request->validated(), $request->user());

        return response()->json($result, $result['success'] ? 201 : 422);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $result = $this->orderService->getOrder($id, $request->user());
        return response()->json($result['data']);
    }

    public function update(OrderUpdateRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        $validated = $request->validated();

        $order->update($validated);

        return response()->json($order->fresh());
    }

    public function generateQR(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        if (!in_array($order->status, [OrderStatus::ASSEMBLING->value, OrderStatus::SHIPPED->value])) {
            return response()->json(['error' => 'Forbidden for this status'], 400);
        }

        $cacheKey = "order_qr_{$order->id}_{$order->updated_at->timestamp}";

        $qrData = Cache::remember($cacheKey, 300, function() use ($order) {
            $order->generateNewQrToken();
            return [
                'qrcode' => $order->getQrCodeBase64(),
                'expires_at' => $order->expires_at,
            ];
        });

        return response()->json($qrData);
    }

    public function updateStatus(Order $order, OrderStatusUpdateRequest $request): JsonResponse
    {
        Gate::authorize('updateStatus', $order);
        $result = $this->orderService->processOrderStatusUpdate($order, $request->validated(), $request->user());

        return response()->json($result, isset($result['error']) ? 403 : 200);
    }
}
