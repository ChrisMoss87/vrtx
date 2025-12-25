<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsCategoryController extends Controller
{
    private const TABLE_CATEGORIES = 'cms_categories';
    private const TABLE_PAGE_CATEGORY = 'cms_category_page';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:cms_categories,id',
            'is_active' => 'nullable|boolean',
            'include_children' => 'nullable|boolean',
        ]);

        $query = DB::table(self::TABLE_CATEGORIES);

        if (array_key_exists('parent_id', $validated)) {
            if ($validated['parent_id'] === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $validated['parent_id']);
            }
        } else {
            $query->whereNull('parent_id');
        }

        if (isset($validated['is_active'])) {
            $query->where('is_active', $validated['is_active']);
        }

        $query->orderBy('sort_order')->orderBy('name');

        $categories = $query->get();

        // Enrich with pages count
        $items = [];
        foreach ($categories as $category) {
            $categoryArray = (array) $category;

            // Count pages
            $pagesCount = DB::table(self::TABLE_PAGE_CATEGORY)
                ->where('cms_category_id', $category->id)
                ->count();
            $categoryArray['pages_count'] = $pagesCount;

            // Load children if requested
            if ($validated['include_children'] ?? false) {
                $children = DB::table(self::TABLE_CATEGORIES)
                    ->where('parent_id', $category->id)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();

                $childrenArray = [];
                foreach ($children as $child) {
                    $childArray = (array) $child;
                    $childArray['pages_count'] = DB::table(self::TABLE_PAGE_CATEGORY)
                        ->where('cms_category_id', $child->id)
                        ->count();
                    $childrenArray[] = $childArray;
                }
                $categoryArray['children'] = $childrenArray;
            }

            $items[] = $categoryArray;
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    public function tree(): JsonResponse
    {
        $rootCategories = DB::table(self::TABLE_CATEGORIES)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $items = [];
        foreach ($rootCategories as $category) {
            $items[] = $this->buildTree($category);
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    private function buildTree($category, int $depth = 0): array
    {
        $categoryArray = (array) $category;

        // Count pages
        $pagesCount = DB::table(self::TABLE_PAGE_CATEGORY)
            ->where('cms_category_id', $category->id)
            ->count();
        $categoryArray['pages_count'] = $pagesCount;

        // Load children recursively (max 3 levels)
        if ($depth < 3) {
            $children = DB::table(self::TABLE_CATEGORIES)
                ->where('parent_id', $category->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $childrenArray = [];
            foreach ($children as $child) {
                $childrenArray[] = $this->buildTree($child, $depth + 1);
            }
            $categoryArray['children'] = $childrenArray;
        }

        return $categoryArray;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_categories,slug',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:cms_categories,id',
            'image' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        // Ensure unique slug
        $originalSlug = $slug;
        $counter = 1;
        while (DB::table(self::TABLE_CATEGORIES)->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $categoryId = DB::table(self::TABLE_CATEGORIES)->insertGetId([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'image' => $validated['image'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $category = DB::table(self::TABLE_CATEGORIES)->where('id', $categoryId)->first();

        return response()->json([
            'data' => (array) $category,
            'message' => 'Category created successfully',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $category = DB::table(self::TABLE_CATEGORIES)->where('id', $id)->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $categoryArray = (array) $category;

        // Load parent
        if ($category->parent_id) {
            $parent = DB::table(self::TABLE_CATEGORIES)
                ->where('id', $category->parent_id)
                ->first();
            $categoryArray['parent'] = $parent ? (array) $parent : null;
        }

        // Load children
        $children = DB::table(self::TABLE_CATEGORIES)
            ->where('parent_id', $id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $childrenArray = [];
        foreach ($children as $child) {
            $childArray = (array) $child;
            $childArray['pages_count'] = DB::table(self::TABLE_PAGE_CATEGORY)
                ->where('cms_category_id', $child->id)
                ->count();
            $childrenArray[] = $childArray;
        }
        $categoryArray['children'] = $childrenArray;

        // Count pages
        $pagesCount = DB::table(self::TABLE_PAGE_CATEGORY)
            ->where('cms_category_id', $id)
            ->count();
        $categoryArray['pages_count'] = $pagesCount;

        // Get breadcrumbs
        $categoryArray['breadcrumbs'] = $this->getBreadcrumbs($category);

        return response()->json([
            'data' => $categoryArray,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = DB::table(self::TABLE_CATEGORIES)->where('id', $id)->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_categories,slug,' . $id,
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:cms_categories,id',
            'image' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        // Prevent moving category into itself or its children
        if (isset($validated['parent_id'])) {
            if ($validated['parent_id'] === $id) {
                return response()->json([
                    'message' => 'Cannot move category into itself',
                ], 422);
            }

            // Check if parent is a descendant
            $parentId = $validated['parent_id'];
            while ($parentId) {
                $parent = DB::table(self::TABLE_CATEGORIES)->where('id', $parentId)->first();
                if (!$parent) {
                    break;
                }
                if ($parent->id === $id) {
                    return response()->json([
                        'message' => 'Cannot move category into its own descendant',
                    ], 422);
                }
                $parentId = $parent->parent_id;
            }
        }

        $updateData = [];
        foreach ($validated as $key => $value) {
            $updateData[$key] = $value;
        }
        $updateData['updated_at'] = now();

        DB::table(self::TABLE_CATEGORIES)->where('id', $id)->update($updateData);

        $updatedCategory = DB::table(self::TABLE_CATEGORIES)->where('id', $id)->first();

        return response()->json([
            'data' => (array) $updatedCategory,
            'message' => 'Category updated successfully',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = DB::table(self::TABLE_CATEGORIES)->where('id', $id)->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Check if category has children
        $hasChildren = DB::table(self::TABLE_CATEGORIES)
            ->where('parent_id', $id)
            ->exists();

        if ($hasChildren) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories',
            ], 422);
        }

        // Detach from pages before deleting
        DB::table(self::TABLE_PAGE_CATEGORY)->where('cms_category_id', $id)->delete();

        DB::table(self::TABLE_CATEGORIES)->where('id', $id)->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:cms_categories,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            DB::table(self::TABLE_CATEGORIES)->where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Categories reordered successfully',
        ]);
    }

    private function getBreadcrumbs($category): array
    {
        $breadcrumbs = [];
        $current = $category;

        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);

            if ($current->parent_id) {
                $current = DB::table(self::TABLE_CATEGORIES)
                    ->where('id', $current->parent_id)
                    ->first();
            } else {
                $current = null;
            }
        }

        return $breadcrumbs;
    }
}
