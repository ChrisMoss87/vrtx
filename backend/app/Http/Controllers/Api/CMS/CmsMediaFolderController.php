<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsMediaFolderController extends Controller
{
    private const TABLE_FOLDERS = 'cms_media_folders';
    private const TABLE_MEDIA = 'cms_media';

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:cms_media_folders,id',
            'include_children' => 'nullable|boolean',
        ]);

        $query = DB::table(self::TABLE_FOLDERS);

        if (array_key_exists('parent_id', $validated)) {
            if ($validated['parent_id'] === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $validated['parent_id']);
            }
        } else {
            $query->whereNull('parent_id');
        }

        $query->orderBy('sort_order')->orderBy('name');

        $folders = $query->get();

        // Enrich with media count
        $items = [];
        foreach ($folders as $folder) {
            $folderArray = (array) $folder;

            // Count media
            $mediaCount = DB::table(self::TABLE_MEDIA)
                ->where('folder_id', $folder->id)
                ->count();
            $folderArray['media_count'] = $mediaCount;

            // Load children if requested
            if ($validated['include_children'] ?? false) {
                $children = DB::table(self::TABLE_FOLDERS)
                    ->where('parent_id', $folder->id)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();

                $childrenArray = [];
                foreach ($children as $child) {
                    $childArray = (array) $child;
                    $childArray['media_count'] = DB::table(self::TABLE_MEDIA)
                        ->where('folder_id', $child->id)
                        ->count();
                    $childrenArray[] = $childArray;
                }
                $folderArray['children'] = $childrenArray;
            }

            $items[] = $folderArray;
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    public function tree(): JsonResponse
    {
        $rootFolders = DB::table(self::TABLE_FOLDERS)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $items = [];
        foreach ($rootFolders as $folder) {
            $items[] = $this->buildTree($folder);
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    private function buildTree($folder, int $depth = 0): array
    {
        $folderArray = (array) $folder;

        // Count media
        $mediaCount = DB::table(self::TABLE_MEDIA)
            ->where('folder_id', $folder->id)
            ->count();
        $folderArray['media_count'] = $mediaCount;

        // Load children recursively (max 3 levels)
        if ($depth < 3) {
            $children = DB::table(self::TABLE_FOLDERS)
                ->where('parent_id', $folder->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $childrenArray = [];
            foreach ($children as $child) {
                $childrenArray[] = $this->buildTree($child, $depth + 1);
            }
            $folderArray['children'] = $childrenArray;
        }

        return $folderArray;
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
        $parentId = $validated['parent_id'] ?? null;
        while (DB::table(self::TABLE_FOLDERS)->where('slug', $slug)->where('parent_id', $parentId)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $folderId = DB::table(self::TABLE_FOLDERS)->insertGetId([
            'name' => $validated['name'],
            'slug' => $slug,
            'parent_id' => $parentId,
            'sort_order' => 0,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $folder = DB::table(self::TABLE_FOLDERS)->where('id', $folderId)->first();

        return response()->json([
            'data' => (array) $folder,
            'message' => 'Folder created successfully',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $folder = DB::table(self::TABLE_FOLDERS)->where('id', $id)->first();

        if (!$folder) {
            return response()->json(['message' => 'Folder not found'], 404);
        }

        $folderArray = (array) $folder;

        // Load parent
        if ($folder->parent_id) {
            $parent = DB::table(self::TABLE_FOLDERS)
                ->where('id', $folder->parent_id)
                ->first();
            $folderArray['parent'] = $parent ? (array) $parent : null;
        }

        // Load children
        $children = DB::table(self::TABLE_FOLDERS)
            ->where('parent_id', $id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $childrenArray = [];
        foreach ($children as $child) {
            $childArray = (array) $child;
            $childArray['media_count'] = DB::table(self::TABLE_MEDIA)
                ->where('folder_id', $child->id)
                ->count();
            $childrenArray[] = $childArray;
        }
        $folderArray['children'] = $childrenArray;

        // Count media
        $mediaCount = DB::table(self::TABLE_MEDIA)
            ->where('folder_id', $id)
            ->count();
        $folderArray['media_count'] = $mediaCount;

        // Get breadcrumbs
        $folderArray['breadcrumbs'] = $this->getBreadcrumbs($folder);

        return response()->json([
            'data' => $folderArray,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $folder = DB::table(self::TABLE_FOLDERS)->where('id', $id)->first();

        if (!$folder) {
            return response()->json(['message' => 'Folder not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer|exists:cms_media_folders,id',
        ]);

        // Prevent moving folder into itself or its children
        if (isset($validated['parent_id'])) {
            if ($validated['parent_id'] === $id) {
                return response()->json([
                    'message' => 'Cannot move folder into itself',
                ], 422);
            }

            // Check if parent is a descendant
            $parentId = $validated['parent_id'];
            while ($parentId) {
                $parent = DB::table(self::TABLE_FOLDERS)->where('id', $parentId)->first();
                if (!$parent) {
                    break;
                }
                if ($parent->id === $id) {
                    return response()->json([
                        'message' => 'Cannot move folder into its own descendant',
                    ], 422);
                }
                $parentId = $parent->parent_id;
            }
        }

        // Handle slug uniqueness
        if (isset($validated['slug']) && $validated['slug'] !== $folder->slug) {
            $parentId = $validated['parent_id'] ?? $folder->parent_id;
            $slug = $validated['slug'];
            $originalSlug = $slug;
            $counter = 1;
            while (DB::table(self::TABLE_FOLDERS)
                ->where('slug', $slug)
                ->where('parent_id', $parentId)
                ->where('id', '!=', $id)
                ->exists()
            ) {
                $slug = $originalSlug . '-' . $counter++;
            }
            $validated['slug'] = $slug;
        }

        $updateData = [];
        foreach ($validated as $key => $value) {
            $updateData[$key] = $value;
        }
        $updateData['updated_at'] = now();

        DB::table(self::TABLE_FOLDERS)->where('id', $id)->update($updateData);

        $updatedFolder = DB::table(self::TABLE_FOLDERS)->where('id', $id)->first();

        return response()->json([
            'data' => (array) $updatedFolder,
            'message' => 'Folder updated successfully',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $folder = DB::table(self::TABLE_FOLDERS)->where('id', $id)->first();

        if (!$folder) {
            return response()->json(['message' => 'Folder not found'], 404);
        }

        // Check if folder has children
        $hasChildren = DB::table(self::TABLE_FOLDERS)
            ->where('parent_id', $id)
            ->exists();

        if ($hasChildren) {
            return response()->json([
                'message' => 'Cannot delete folder with subfolders',
            ], 422);
        }

        // Check if folder has media
        $hasMedia = DB::table(self::TABLE_MEDIA)
            ->where('folder_id', $id)
            ->exists();

        if ($hasMedia) {
            return response()->json([
                'message' => 'Cannot delete folder containing media. Move or delete the media first.',
            ], 422);
        }

        DB::table(self::TABLE_FOLDERS)->where('id', $id)->delete();

        return response()->json([
            'message' => 'Folder deleted successfully',
        ]);
    }

    private function getBreadcrumbs($folder): array
    {
        $breadcrumbs = [];
        $current = $folder;

        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);

            if ($current->parent_id) {
                $current = DB::table(self::TABLE_FOLDERS)
                    ->where('id', $current->parent_id)
                    ->first();
            } else {
                $current = null;
            }
        }

        return $breadcrumbs;
    }
}
