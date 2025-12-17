<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Http\Controllers\Controller;
use App\Models\CmsMedia;
use App\Models\CmsMediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsMediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folder_id' => 'nullable|integer|exists:cms_media_folders,id',
            'type' => 'nullable|string|in:image,document,video,audio,other',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:name,created_at,size,type',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $query = CmsMedia::query()->with('folder:id,name');

        if (array_key_exists('folder_id', $validated)) {
            $query->inFolder($validated['folder_id']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $validated['per_page'] ?? 50;
        $media = $query->paginate($perPage);

        return response()->json([
            'data' => $media->items(),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
            'folder_id' => 'nullable|integer|exists:cms_media_folders,id',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        $file = $request->file('file');
        $mimeType = $file->getMimeType();
        $type = CmsMedia::determineType($mimeType);
        $originalName = $file->getClientOriginalName();
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;

        // Store in tenant-specific directory
        $path = $file->storeAs('cms/media/' . date('Y/m'), $filename, 'public');

        $media = CmsMedia::create([
            'name' => $name,
            'filename' => $originalName,
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'type' => $type,
            'alt_text' => $validated['alt_text'] ?? null,
            'caption' => $validated['caption'] ?? null,
            'description' => $validated['description'] ?? null,
            'folder_id' => $validated['folder_id'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'uploaded_by' => Auth::id(),
        ]);

        // Get image dimensions if it's an image
        if ($type === CmsMedia::TYPE_IMAGE) {
            $fullPath = Storage::disk('public')->path($path);
            $imageInfo = @getimagesize($fullPath);
            if ($imageInfo) {
                $media->update([
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                ]);
            }
        }

        return response()->json([
            'data' => $media->fresh(),
            'message' => 'Media uploaded successfully',
        ], 201);
    }

    public function show(CmsMedia $cmsMedia): JsonResponse
    {
        $cmsMedia->load(['folder', 'uploader:id,name']);

        return response()->json([
            'data' => $cmsMedia,
        ]);
    }

    public function update(Request $request, CmsMedia $cmsMedia): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'description' => 'nullable|string',
            'folder_id' => 'nullable|integer|exists:cms_media_folders,id',
            'tags' => 'nullable|array',
        ]);

        $cmsMedia->update($validated);

        return response()->json([
            'data' => $cmsMedia->fresh(),
            'message' => 'Media updated successfully',
        ]);
    }

    public function destroy(CmsMedia $cmsMedia): JsonResponse
    {
        // Delete the actual file
        Storage::disk($cmsMedia->disk)->delete($cmsMedia->path);

        $cmsMedia->forceDelete();

        return response()->json([
            'message' => 'Media deleted successfully',
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:cms_media,id',
        ]);

        $media = CmsMedia::whereIn('id', $validated['ids'])->get();

        foreach ($media as $item) {
            Storage::disk($item->disk)->delete($item->path);
            $item->forceDelete();
        }

        return response()->json([
            'message' => count($validated['ids']) . ' items deleted successfully',
        ]);
    }

    public function move(Request $request, CmsMedia $cmsMedia): JsonResponse
    {
        $validated = $request->validate([
            'folder_id' => 'nullable|integer|exists:cms_media_folders,id',
        ]);

        $cmsMedia->update(['folder_id' => $validated['folder_id']]);

        return response()->json([
            'data' => $cmsMedia->fresh(),
            'message' => 'Media moved successfully',
        ]);
    }

    public function bulkMove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:cms_media,id',
            'folder_id' => 'nullable|integer|exists:cms_media_folders,id',
        ]);

        CmsMedia::whereIn('id', $validated['ids'])->update(['folder_id' => $validated['folder_id']]);

        return response()->json([
            'message' => count($validated['ids']) . ' items moved successfully',
        ]);
    }

    public function stats(): JsonResponse
    {
        $totalSize = CmsMedia::sum('size');
        $counts = CmsMedia::selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return response()->json([
            'data' => [
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'counts' => $counts,
                'total_count' => array_sum($counts),
            ],
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
