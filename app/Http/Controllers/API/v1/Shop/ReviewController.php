<?php

namespace App\Http\Controllers\API\v1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewCreateRequest;
use App\Http\Requests\ReviewUpdateRequest;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {}

    public function index(Product $product): JsonResponse
    {
        return response()->json($this->reviewService->getReviews($product, auth('api')->user()));
    }

    public function getProductsWithoutReview(Request $request): JsonResponse
    {
        return response()->json(['products' => $this->reviewService->getProductsToReview($request->user())]);
    }

    public function store(ReviewCreateRequest $request, Product $product): JsonResponse
    {
        try {
            $review = $this->reviewService->createReview(
                $product,
                $request->user(),
                $request->validated(),
                ['photos' => $request->file('photos'), 'video' => $request->file('video')]
            );
            return response()->json($review, 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode());
        }
    }

    public function update(ReviewUpdateRequest $request, Review $review): JsonResponse
    {
        Gate::authorize('update', $review);
        $updated = $this->reviewService->updateReview(
            $review,
            $request->validated(),
            ['photos' => $request->file('photos'), 'video' => $request->file('video')]
        );
        return response()->json($updated);
    }

    public function destroy(Review $review): JsonResponse
    {
        Gate::authorize('delete', $review);
        $this->reviewService->deleteReview($review);
        return response()->json(['message' => 'Отзыв удален']);
    }

    public function markHelpful(Review $review): JsonResponse
    {
        return response()->json($this->reviewService->toggleHelpful($review, auth()->user()));
    }

    public function userReviews(Request $request, User $user): JsonResponse
    {
        $reviews = $this->reviewService->getUserReviews($user, $request->all());
        return response()->json($reviews);
    }
}
