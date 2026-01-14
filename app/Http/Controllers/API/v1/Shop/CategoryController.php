<?php

namespace App\Http\Controllers\API\v1\Shop;

use App\Http\Controllers\Controller;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function getCategoriesWithQuestions(Request $request): JsonResponse
    {
        $tree = $this->categoryService->getCategoryTree(
            (int) $request->get('limit', 20),
            (string) $request->get('search', '')
        );

        return response()->json($tree);
    }

    public function getQuestionsByCategory(Request $request, int $categoryId): JsonResponse
    {
        $questions = $this->categoryService->getQuestionsByCategory(
            $categoryId,
            (int) $request->get('limit', 20)
        );

        return response()->json($questions);
    }

    public function getSubcategories(Request $request, int $parentId): JsonResponse
    {
        $subcategories = $this->categoryService->getSubcategories(
            $parentId,
            (int) $request->get('limit', 20),
            (string) $request->get('search', '')
        );

        return response()->json($subcategories);
    }
}
