<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CollaborativeDocument;

use App\Application\Services\CollaborativeDocument\CollaborativeDocumentApplicationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DocumentFolderController extends Controller
{
    public function __construct(
        private CollaborativeDocumentApplicationService $documentService,
    ) {}

    /**
     * Get folder tree structure.
     */
    public function tree(): JsonResponse
    {
        $tree = $this->documentService->getFolderTree();

        return response()->json(['data' => $tree]);
    }

    /**
     * Get folder contents (documents and subfolders).
     */
    public function contents(Request $request): JsonResponse
    {
        $folderId = $request->input('folder_id');

        try {
            $contents = $this->documentService->getFolderContents(
                folderId: $folderId ? (int) $folderId : null,
            );

            return response()->json(['data' => $contents]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a new folder.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:collaborative_document_folders,id',
            'color' => 'nullable|string|max:7', // e.g. #FF5733
        ]);

        try {
            $folder = $this->documentService->createFolder(
                name: $validated['name'],
                parentId: $validated['parent_id'] ?? null,
                color: $validated['color'] ?? null,
            );

            return response()->json([
                'message' => 'Folder created successfully',
                'data' => $folder,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update a folder.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'parent_id' => 'sometimes|nullable|integer',
            'color' => 'sometimes|nullable|string|max:7',
        ]);

        try {
            $folder = $this->documentService->updateFolder($id, $validated);

            return response()->json([
                'message' => 'Folder updated successfully',
                'data' => $folder,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete a folder (moves contents to parent).
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->documentService->deleteFolder($id);

            return response()->json(['message' => 'Folder deleted successfully']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
