<?php

namespace App\Http\Controllers\API\v1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductCreateRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $products = $this->productService->getProducts($request->user(), $request->all());
            return response()->json($products);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

    public function store(ProductCreateRequest $request): JsonResponse
    {
        $product = $this->productService->createProduct(
            $request->user(),
            $request->validated(),
            $request->file('files')
        );
        return response()->json($product, 201);
    }

    public function show(Product $product, Request $request): JsonResponse
    {
        try {
            $enrichedProduct = $this->productService->getProduct($product, $request->user());
            return response()->json($enrichedProduct);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function update(ProductUpdateRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $updated = $this->productService->updateProduct(
            $product,
            $request->validated(),
            $request->file('files'),
            $request->input('price_currency')
        );

        return response()->json($updated);
    }

    public function destroy(Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
