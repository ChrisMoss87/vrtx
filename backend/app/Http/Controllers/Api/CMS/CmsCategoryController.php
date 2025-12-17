<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use App\Models\CmsCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CmsCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:cms_categories,id',
            'is_active' => 'nullable|boolean',
            'include_children' => 'nullable|boolean',
        ]);

        $query = CmsCategory::query()->withCount('pages');

        if (array_key_exists('parent_id', $validated)) {
            if ($validated['parent_id'] === null) {
                $query->root();
            } else {
                $query->where('parent_id', $validated['parent_id']);
            }
        } else {
            $query->root();
        }

        if (isset($validated['is_active'])) {
            if ($validated['is_active']) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if ($validated['include_children'] ?? false) {
            $query->with(['children' => fn($q) => $q->withCount('pages')->ordered()]);
        }

        $categories = $query->ordered()->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function tree(): JsonResponse
    {
        $categories = CmsCategory::root()
            ->active()
            ->with(['children' => function ($query) {
                $query->active()->withCount('pages')->ordered()->with(['children' => function ($q) {
                    $q->active()->withCount('pages')->ordered();
                }]);
            }])
            ->withCount('pages')
            ->ordered()
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
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
        while (CmsCategory::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $category = CmsCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'image' => $validated['image'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => $category,
            'message' => 'Category created successfully',
        ], 201);
    }

    public function show(CmsCategory $cmsCategory): JsonResponse
    {
        $cmsCategory->load(['parent', 'children' => fn($q) => $q->withCount('pages')->ordered()]);
        $cmsCategory->loadCount('pages');

        return response()->json([
            'data' => $cmsCategory,
            'breadcrumbs' => $this->getBreadcrumbs($cmsCategory),
        ]);
    }

    public function update(Request $request, CmsCategory $cmsCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|unique:cms_categories,slug,' . $cmsCategory->id,
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:cms_categories,id',
            'image' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        // Prevent moving category into itself or its children
        if (isset($validated['parent_id'])) {
            if ($validated['parent_id'] === $cmsCategory->id) {
                return response()->json([
                    'message' => 'Cannot move category into itself',
                ], 422);
            }

            $parent = CmsCategory::find($validated['parent_id']);
            while ($parent) {
                if ($parent->id === $cmsCategory->id) {
                    return response()->json([
                        'message' => 'Cannot move category into its own descendant',
                    ], 422);
                }
                $parent = $parent->parent;
            }
        }

        $cmsCategory->update($validated);

        return response()->json([
            'data' => $cmsCategory->fresh(),
            'message' => 'Category updated successfully',
        ]);
    }

    public function destroy(CmsCategory $cmsCategory): JsonResponse
    {
        // Check if category has children
        if ($cmsCategory->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories',
            ], 422);
        }

        // Detach from pages before deleting
        $cmsCategory->pages()->detach();
        $cmsCategory->delete();

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
            CmsCategory::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'message' => 'Categories reordered successfully',
        ]);
    }

    private function getBreadcrumbs(CmsCategory $category): array
    {
        $breadcrumbs = [];
        $current = $category;

        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }
}
