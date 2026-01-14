<?php

namespace App\Http\Controllers\API\v1\System;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Services\DashboardService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getSellerMetrics(
            $request->user(),
            $request->get('period', 'day')
        );

        return response()->json($data);
    }

    public function quickConfirmOrder(SellerOrder $sellerOrder): JsonResponse
    {
        $this->authorize('update', $sellerOrder);

        $updatedOrder = $this->dashboardService->confirmOrder($sellerOrder);

        return response()->json([
            'message' => 'Заказ успешно подтвержден и передан в сборку.',
            'data' => $updatedOrder
        ]);
    }
}
