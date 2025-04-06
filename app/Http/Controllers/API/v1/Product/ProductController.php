<?php

namespace App\Http\Controllers\API\v1\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'files' => 'required|array',
                'files.*' => 'file|mimes:jpeg,png,jpg,gif,mp4',
                'content' => 'nullable|string',
                'price' => 'required',
                'discount' => 'nullable|integer',
            //    'quantity',
            //    'condition',
            //    'refund',
            //    'inStock',
            //    'points',
            //    'views_count',
            //    'shares_count',
            //    'likes_count',
            //    'reviews_count',
            ]);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        }

        $post = $request->user()->posts()->create([
            'content' => $validated['content'] ?? null,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('post_files', 'public');
                $post->files()->create([
                    'file_path' => $path,
                    'file_type' => strtok($file->getClientMimeType(), '/'),
                ]);
            }
        }

        return response()->json($post, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
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
    public function destroy(Product $product)
    {
        //
    }
}
