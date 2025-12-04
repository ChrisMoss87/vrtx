<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class FileUploadController extends Controller
{
    /**
     * Upload a file.
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
            'type' => 'nullable|string|in:file,image',
            'module' => 'nullable|string|max:100',
            'field' => 'nullable|string|max:100',
        ]);

        $file = $request->file('file');
        $type = $validated['type'] ?? 'file';
        $module = $validated['module'] ?? 'general';
        $field = $validated['field'] ?? 'uploads';

        // Additional validation for images
        if ($type === 'image') {
            $request->validate([
                'file' => 'image|mimes:jpeg,png,gif,webp|max:10240', // 10MB for images
            ]);
        }

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = Str::slug($originalName);
        $uniqueName = $safeName . '_' . Str::random(8) . '.' . $extension;

        // Build storage path: uploads/{tenant}/{module}/{field}/{filename}
        $tenantId = tenant('id') ?? 'default';
        $path = "uploads/{$tenantId}/{$module}/{$field}";

        // Store the file
        $storedPath = $file->storeAs($path, $uniqueName, 'public');

        if (!$storedPath) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Generate URL
        $url = Storage::disk('public')->url($storedPath);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => Str::uuid()->toString(),
                'name' => $file->getClientOriginalName(),
                'filename' => $uniqueName,
                'path' => $storedPath,
                'url' => $url,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $extension,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Upload multiple files.
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'files' => 'required|array|max:10',
            'files.*' => 'file|max:51200',
            'type' => 'nullable|string|in:file,image',
            'module' => 'nullable|string|max:100',
            'field' => 'nullable|string|max:100',
        ]);

        $type = $validated['type'] ?? 'file';
        $module = $validated['module'] ?? 'general';
        $field = $validated['field'] ?? 'uploads';

        // Additional validation for images
        if ($type === 'image') {
            $request->validate([
                'files.*' => 'image|mimes:jpeg,png,gif,webp|max:10240',
            ]);
        }

        $tenantId = tenant('id') ?? 'default';
        $path = "uploads/{$tenantId}/{$module}/{$field}";

        $uploaded = [];
        $errors = [];

        foreach ($request->file('files') as $index => $file) {
            try {
                $extension = $file->getClientOriginalExtension();
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = Str::slug($originalName);
                $uniqueName = $safeName . '_' . Str::random(8) . '.' . $extension;

                $storedPath = $file->storeAs($path, $uniqueName, 'public');

                if ($storedPath) {
                    $uploaded[] = [
                        'id' => Str::uuid()->toString(),
                        'name' => $file->getClientOriginalName(),
                        'filename' => $uniqueName,
                        'path' => $storedPath,
                        'url' => Storage::disk('public')->url($storedPath),
                        'size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'extension' => $extension,
                    ];
                } else {
                    $errors[] = [
                        'index' => $index,
                        'name' => $file->getClientOriginalName(),
                        'error' => 'Failed to store file',
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'data' => [
                'uploaded' => $uploaded,
                'errors' => $errors,
                'total' => count($uploaded) + count($errors),
                'successful' => count($uploaded),
                'failed' => count($errors),
            ],
        ], count($uploaded) > 0 ? Response::HTTP_CREATED : Response::HTTP_BAD_REQUEST);
    }

    /**
     * Delete a file.
     */
    public function delete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
        ]);

        $path = $validated['path'];

        // Security check: ensure path belongs to current tenant
        $tenantId = tenant('id') ?? 'default';
        if (!Str::startsWith($path, "uploads/{$tenantId}/")) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], Response::HTTP_FORBIDDEN);
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'File not found',
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Get file info.
     */
    public function info(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string',
        ]);

        $path = $validated['path'];

        // Security check
        $tenantId = tenant('id') ?? 'default';
        if (!Str::startsWith($path, "uploads/{$tenantId}/")) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], Response::HTTP_FORBIDDEN);
        }

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'size' => Storage::disk('public')->size($path),
                'last_modified' => Storage::disk('public')->lastModified($path),
            ],
        ]);
    }
}
