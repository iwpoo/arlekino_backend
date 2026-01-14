<?php

namespace App\Http\Controllers\API\v1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerQuestionRequest;
use App\Http\Requests\StoreQuestionRequest;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Services\ProductQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProductQuestionController extends Controller
{
    public function __construct(
        protected ProductQuestionService $productQuestionService
    ) {}

    public function index(Product $product): JsonResponse
    {
        $questions = $this->productQuestionService->getQuestions($product, auth()->user());
        return response()->json($questions);
    }

    public function store(StoreQuestionRequest $request, Product $product): JsonResponse
    {
        $question = $this->productQuestionService->createQuestion($product, $request->user(), $request->validated());
        return response()->json($question, 201);
    }

    public function answer(AnswerQuestionRequest $request, ProductQuestion $question): JsonResponse
    {
        Gate::authorize('answer', $question);

        $updated = $this->productQuestionService->updateQuestionAnswer($question, $request->user(), $request->validated());
        return response()->json($updated);
    }

    public function updateAnswer(AnswerQuestionRequest $request, ProductQuestion $question): JsonResponse
    {
        Gate::authorize('answer', $question);

        $updated = $this->productQuestionService->updateQuestionAnswer($question, $request->user(), $request->validated());
        return response()->json($updated);
    }

    public function destroy(ProductQuestion $question): JsonResponse
    {
        Gate::authorize('delete', $question);

        $this->productQuestionService->deleteQuestion($question);
        return response()->json(null, 204);
    }

    public function markHelpful(ProductQuestion $question): JsonResponse
    {
        $result = $this->productQuestionService->toggleHelpful($question, auth()->user());
        return response()->json($result);
    }
}

