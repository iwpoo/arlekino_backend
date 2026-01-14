<?php

namespace App\Http\Controllers\API\v1\System;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        protected SearchService $searchService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'nullable|string|max:100',
            'type'  => 'nullable|in:all,products,posts,shops,customers',
            'sort'  => 'nullable|in:relevance,newest,price_low,popularity'
        ]);

        $result = $this->searchService->performSearch($request);

        return response()->json($result);
    }
}
