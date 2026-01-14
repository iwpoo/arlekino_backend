<?php

namespace App\Http\Controllers\API\v1\System;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->analyticsService->getAnalyticsData(
            $request->user(),
            $request->query('period', 'month')
        );

        return response()->json($data);
    }
}
