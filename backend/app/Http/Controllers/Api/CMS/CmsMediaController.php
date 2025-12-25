<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CMS;

use App\Domain\CMS\Entities\CmsMedia;
use App\Domain\CMS\Repositories\CmsMediaRepositoryInterface;
use App\Domain\CMS\ValueObjects\MediaType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsMediaController extends Controller
{
    private const TABLE_MEDIA = 'cms_media';
    private const TABLE_FOLDERS = 'cms_media_folders';
    private const TABLE_USERS = 'users';

    public function __construct(
        private readonly CmsMediaRepositoryInterface $mediaRepository
    ) {}

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

        $filters = [];
        if (array_key_exists('folder_id', $validated)) {
            $filters['folder_id'] = $validated['folder_id'];
        }
        if (isset($validated['type'])) {
            $filters['type'] = $validated['type'];
        }
        if (isset($validated['search'])) {
            $filters['search'] = $validated['search'];
        }
        $filters['sort_by'] = $validated['sort_by'] ?? 'created_at';
        $filters['sort_dir'] = $validated['sort_order'] ?? 'desc';

        $perPage = $validated['per_page'] ?? 50;
        $page = $request->integer('page', 1);

        $result = $this->mediaRepository->paginate($filters, $perPage, $page);

        return response()->json([
            'data' => $result->items,
            'meta' => [
                'current_page' => $result->currentPage,
                'last_page' => $result->lastPage,
                'per_page' => $result->perPage,
                'total' => $result->total,
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
        $type = MediaType::fromMimeType($mimeType);
        $originalName = $file->getClientOriginalName();
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;

        // Store in tenant-specific directory
        $path = $file->storeAs('cms/media/' . date('Y/m'), $filename, 'public');

        $media = CmsMedia::create(
            name: $name,
            filename: $originalName,
            path: $path,
            mimeType: $mimeType,
            size: $file->getSize(),
            type: $type,
            uploadedBy: Auth::id(),
        );

        // Save with additional fields using DB for flexibility
        $mediaData = [
            'name' => $name,
            'filename' => $originalName,
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'type' => $type->value,
            'alt_text' => $validated['alt_text'] ?? null,
            'caption' => $validated['caption'] ?? null,
            'description' => $validated['description'] ?? null,
            'folder_id' => $validated['folder_id'] ?? null,
            'tags' => isset($validated['tags']) ? json_encode($validated['tags']) : null,
            'uploaded_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $mediaId = DB::table(self::TABLE_MEDIA)->insertGetId($mediaData);

        // Get image dimensions if it's an image
        if ($type === MediaType::IMAGE) {
            $fullPath = Storage::disk('public')->path($path);
            $imageInfo = @getimagesize($fullPath);
            if ($imageInfo) {
                DB::table(self::TABLE_MEDIA)->where('id', $mediaId)->update([
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                ]);
            }
        }

        $mediaArray = $this->mediaRepository->findByIdAsArray($mediaId);

        return response()->json([
            'data' => $mediaArray,
            'message' => 'Media uploaded successfully',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $mediaArray = $this->mediaRepository->findByIdAsArray($id);

        if (!$mediaArray) {
            return response()->json(['message' => 'Media not found'], 404);
        }

        // Load uploader
        if ($mediaArray['uploaded_by']) {
            $uploader = DB::table(self::TABLE_USERS)
                ->where('id', $mediaArray['uploaded_by'])
                ->select(['id', 'name'])
                ->first();
            $mediaArray['uploader'] = $uploader ? (array) $uploader : null;
        }

        return response()->json([
            'data' => $mediaArray,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $media = $this->mediaRepository->findById($id);

        if (!$media) {
            return response()->json(['message' => 'Media not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'description' => 'nullable|string',
            'folder_id' => 'nullable|integer|exists:cms_media_folders,id',
            'tags' => 'nullable|array',
        ]);

        $updateData = [];
        foreach ($validated as $key => $value) {
            if ($key === 'tags') {
                $updateData[$key] = is_array($value) ? json_encode($value) : $value;
            } else {
                $updateData[$key] = $value;
            }
        }
        $updateData['updated_at'] = now();

        DB::table(self::TABLE_MEDIA)->where('id', $id)->update($updateData);

        $mediaArray = $this->mediaRepository->findByIdAsArray($id);

        return response()->json([
            'data' => $mediaArray,
            'message' => 'Media updated successfully',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $media = $this->mediaRepository->findById($id);

        if (!$media) {
            return response()->json(['message' => 'Media not found'], 404);
        }

        // Delete the actual file
        Storage::disk($media->getDisk())->delete($media->getPath());

        $this->mediaRepository->delete($id);

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

        $mediaItems = $this->mediaRepository->findByIds($validated['ids']);

        foreach ($mediaItems as $media) {
            Storage::disk($media->getDisk())->delete($media->getPath());
            $this->mediaRepository->delete($media->getId());
        }

        return response()->json([
            'message' => count($validated['ids']) . ' items deleted successfully',
        ]);
    }

    public function move(Request $request, int $id): JsonResponse
    {
        $media = $this->mediaRepository->findById($id);

        if (!$media) {
            return response()->json(['message' => 'Media not found'], 404);
        }

        $validated = $request->validate([
            'folder_id' => 'nullable|integer|exists:cms_media_folders,id',
        ]);

        DB::table(self::TABLE_MEDIA)->where('id', $id)->update([
            'folder_id' => $validated['folder_id'],
            'updated_at' => now(),
        ]);

        $mediaArray = $this->mediaRepository->findByIdAsArray($id);

        return response()->json([
            'data' => $mediaArray,
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

        DB::table(self::TABLE_MEDIA)->whereIn('id', $validated['ids'])->update([
            'folder_id' => $validated['folder_id'],
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => count($validated['ids']) . ' items moved successfully',
        ]);
    }

    public function stats(): JsonResponse
    {
        $totalSize = $this->mediaRepository->getTotalSize();
        $countsArray = $this->mediaRepository->getCountByType();

        return response()->json([
            'data' => [
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'counts' => $countsArray,
                'total_count' => array_sum($countsArray),
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
