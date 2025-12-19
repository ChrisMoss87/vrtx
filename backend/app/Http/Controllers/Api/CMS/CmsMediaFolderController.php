<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use App\Models\CmsMediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CmsMediaFolderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:cms_media_folders,id',
            'include_children' => 'nullable|boolean',
        ]);

        $query = CmsMediaFolder::query()->withCount('media');

        if (array_key_exists('parent_id', $validated)) {
            if ($validated['parent_id'] === null) {
                $query->root();
            } else {
                $query->where('parent_id', $validated['parent_id']);
            }
        } else {
            $query->root();
        }

        if ($validated['include_children'] ?? false) {
            $query->with(['children' => fn($q) => $q->withCount('media')->ordered()]);
        }

        $folders = $query->ordered()->get();

        return response()->json([
            'data' => $folders,
        ]);
    }

    public function tree(): JsonResponse
    {
        $folders = CmsMediaFolder::root()
            ->with(['children' => function ($query) {
                $query->withCount('media')->ordered()->with(['children' => function ($q) {
                    $q->withCount('media')->ordered();
                }]);
            }])
            ->withCount('media')
            ->ordered()
            ->get();

        return response()->json([
            'data' => $folders,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer|exists:cms_media_folders,id',
        ]);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);

        // Ensure unique slug within parent
        $originalSlug = $slug;
        $counter = 1;
        while (CmsMediaFolder::where('slug', $slug)->where('parent_id', $validated['parent_id'] ?? null)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $folder = CmsMediaFolder::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'parent_id' => $validated['parent_id'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'data' => $folder,
            'message' => 'Folder created successfully',
        ], 201);
    }

    public function show(CmsMediaFolder $cmsMediaFolder): JsonResponse
    {
        $cmsMediaFolder->load(['parent', 'children' => fn($q) => $q->withCount('media')->ordered()]);
        $cmsMediaFolder->loadCount('media');

        return response()->json([
            'data' => $cmsMediaFolder,
            'breadcrumbs' => $this->getBreadcrumbs($cmsMediaFolder),
        ]);
    }

    public function update(Request $request, CmsMediaFolder $cmsMediaFolder): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer|exists:cms_media_folders,id',
        ]);

        // Prevent moving folder into itself or its children
        if (isset($validated['parent_id'])) {
            if ($validated['parent_id'] === $cmsMediaFolder->id) {
                return response()->json([
                    'message' => 'Cannot move folder into itself',
                ], 422);
            }

            // Check if parent is a descendant
            $parent = CmsMediaFolder::find($validated['parent_id']);
            while ($parent) {
                if ($parent->id === $cmsMediaFolder->id) {
                    return response()->json([
                        'message' => 'Cannot move folder into its own descendant',
                    ], 422);
                }
                $parent = $parent->parent;
            }
        }

        // Handle slug uniqueness
        if (isset($validated['slug']) && $validated['slug'] !== $cmsMediaFolder->slug) {
            $parentId = $validated['parent_id'] ?? $cmsMediaFolder->parent_id;
            $slug = $validated['slug'];
            $originalSlug = $slug;
            $counter = 1;
            while (CmsMediaFolder::where('slug', $slug)
                ->where('parent_id', $parentId)
                ->where('id', '!=', $cmsMediaFolder->id)
                ->exists()
            ) {
                $slug = $originalSlug . '-' . $counter++;
            }
            $validated['slug'] = $slug;
        }

        $cmsMediaFolder->update($validated);

        return response()->json([
            'data' => $cmsMediaFolder->fresh(),
            'message' => 'Folder updated successfully',
        ]);
    }

    public function destroy(CmsMediaFolder $cmsMediaFolder): JsonResponse
    {
        // Check if folder has children or media
        if ($cmsMediaFolder->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete folder with subfolders',
            ], 422);
        }

        if ($cmsMediaFolder->media()->exists()) {
            return response()->json([
                'message' => 'Cannot delete folder containing media. Move or delete the media first.',
            ], 422);
        }

        $cmsMediaFolder->delete();

        return response()->json([
            'message' => 'Folder deleted successfully',
        ]);
    }

    private function getBreadcrumbs(CmsMediaFolder $folder): array
    {
        $breadcrumbs = [];
        $current = $folder;

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
