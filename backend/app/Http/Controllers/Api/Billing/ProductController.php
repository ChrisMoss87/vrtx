<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category');

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $products = $query->orderBy('name')->get();

        return response()->json([
            'data' => $products,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'description' => 'nullable|string',
            'unit_price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'category_id' => 'nullable|exists:product_categories,id',
            'unit' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'data' => $product->load('category'),
            'message' => 'Product created successfully',
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $product->load('category'),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $product->id,
            'description' => 'nullable|string',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'category_id' => 'nullable|exists:product_categories,id',
            'unit' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
        ]);

        $product->update($validated);

        return response()->json([
            'data' => $product->fresh('category'),
            'message' => 'Product updated successfully',
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    // Product Categories
    public function categories(): JsonResponse
    {
        $categories = ProductCategory::with('children')
            ->whereNull('parent_id')
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:product_categories,id',
            'display_order' => 'nullable|integer',
        ]);

        $category = ProductCategory::create($validated);

        return response()->json([
            'data' => $category,
            'message' => 'Category created successfully',
        ], 201);
    }

    public function updateCategory(Request $request, ProductCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:product_categories,id',
            'display_order' => 'nullable|integer',
        ]);

        $category->update($validated);

        return response()->json([
            'data' => $category,
            'message' => 'Category updated successfully',
        ]);
    }

    public function destroyCategory(ProductCategory $category): JsonResponse
    {
        // Move products to uncategorized
        Product::where('category_id', $category->id)->update(['category_id' => null]);

        // Move child categories to parent or root
        ProductCategory::where('parent_id', $category->id)->update(['parent_id' => $category->parent_id]);

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
