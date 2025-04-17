<?php

namespace App\Http\Controllers\API\v1\Product;

use App\Enums\DiscountType;
use App\Enums\ItemCondition;
use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $type = $request->query('type', 'subscriptions');
        $perPage = $request->query('per_page', 10);

        if ($type === 'subscriptions') {
            $followingIds = Follow::where('follower_id', $userId)
                ->pluck('following_id');

            $posts = Product::whereIn('products.user_id', $followingIds)
                ->with(['user', 'files'])
                ->orderByDesc('products.created_at')
                ->paginate($perPage);
        } elseif ($type === 'recommendations') {
            $posts = Product::with(['user', 'files'])
                ->orderByDesc('likes_count')
                ->paginate($perPage);
        } else {
            return response()->json(['error' => 'Invalid type parameter'], 400);
        }

        return response()->json($posts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'files' => 'required|array',
                'files.*' => 'file|mimes:jpeg,png,jpg,gif,mp4',
                'title' => 'required|string|max:255',
                'content' => 'nullable|string|max:5000',
                'price' => 'required|numeric|min:0',
                'discountType' => ['nullable', 'string', DiscountType::rule()],
                'discountValue' => 'nullable|integer|min:0',
                'quantity' => 'required|integer|min:0',
                'condition' => ['required', 'string', ItemCondition::rule()],
                'refund' => 'nullable|boolean',
                'inStock' => 'nullable|boolean',
                'points' => 'required|string',
                'category_id' => 'required|integer|exists:categories,id',
                'attributes' => 'required|string',
                'views_count' => 'nullable|integer|min:0',
                'shares_count' => 'nullable|integer|min:0',
                'likes_count' => 'nullable|integer|min:0',
                'reviews_count' => 'nullable|integer|min:0',
            ]);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        }

        $product = $request->user()->products()->create([
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'price' => $validated['price'],
            'discountType' => $validated['discountType'] ?? null,
            'discountValue' => $validated['discountValue'] ?? null,
            'quantity' => $validated['quantity'],
            'condition' => $validated['condition'],
            'refund' => $validated['refund'],
            'inStock' => $validated['inStock'],
            'points' => $validated['points'],
            'category_id' => $validated['category_id'],
            'attributes' => $validated['attributes'],
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('product_files', 'public');
                $product->files()->create([
                    'file_path' => $path,
                    'file_type' => strtok($file->getClientMimeType(), '/'),
                ]);
            }
        }

        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['user', 'files'])->withVariants()->similarProducts();
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        if ($product->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }
}
