<?php

namespace App\Http\Controllers\API\v1\Marketing;

use App\Http\Controllers\Controller;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    protected RecommendationService $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    public function getHybridFeed(Request $request): JsonResponse
    {
        $feed = $this->recommendationService->getHybridFeed(
            $request->user(),
            (int) $request->query('limit', 20)
        );

        return response()->json([
            'status' => 'success',
            'data' => $feed
        ]);
    }

    public function getDiscoveryFeed(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int) $request->query('limit', 20);

        $feed = $this->recommendationService->getDiscoveryFeed($user, $limit);

        return response()->json($feed);
    }
}
