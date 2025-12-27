<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CollaborativeDocument;

use App\Application\Services\CollaborativeDocument\CollaborativeDocumentApplicationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DocumentController extends Controller
{
    public function __construct(
        private CollaborativeDocumentApplicationService $documentService,
    ) {}

    /**
     * List documents with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'type',
            'folder_id',
            'is_template',
            'search',
            'sort_by',
            'sort_direction',
        ]);

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page = max((int) $request->input('page', 1), 1);

        $result = $this->documentService->listDocuments($filters, $perPage, $page);

        return response()->json($result->toArray());
    }

    /**
     * Get recent documents.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 10), 50);
        $documents = $this->documentService->getRecentDocuments($limit);

        return response()->json(['data' => $documents]);
    }

    /**
     * Create a new document.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:word,spreadsheet,presentation',
            'folder_id' => 'nullable|integer|exists:collaborative_document_folders,id',
        ]);

        try {
            $document = $this->documentService->createDocument(
                title: $validated['title'],
                type: $validated['type'],
                folderId: $validated['folder_id'] ?? null,
            );

            return response()->json([
                'message' => 'Document created successfully',
                'data' => $document,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get a specific document.
     */
    public function show(int $id): JsonResponse
    {
        $document = $this->documentService->getDocument($id);

        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        return response()->json(['data' => $document]);
    }

    /**
     * Update document metadata.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'folder_id' => 'sometimes|nullable|integer',
            'is_template' => 'sometimes|boolean',
        ]);

        try {
            $document = $this->documentService->updateDocument($id, $validated);

            return response()->json([
                'message' => 'Document updated successfully',
                'data' => $document,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete a document.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->documentService->deleteDocument($id);

            return response()->json(['message' => 'Document deleted successfully']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Duplicate a document.
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
        ]);

        try {
            $document = $this->documentService->duplicateDocument(
                id: $id,
                title: $validated['title'] ?? null,
            );

            return response()->json([
                'message' => 'Document duplicated successfully',
                'data' => $document,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Create document from template.
     */
    public function createFromTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'folder_id' => 'nullable|integer',
        ]);

        try {
            $document = $this->documentService->createFromTemplate(
                templateId: $validated['template_id'],
                title: $validated['title'],
                folderId: $validated['folder_id'] ?? null,
            );

            return response()->json([
                'message' => 'Document created from template',
                'data' => $document,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Search documents.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $limit = min((int) $request->input('limit', 50), 100);

        if (strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $results = $this->documentService->searchDocuments($query, $limit);

        return response()->json(['data' => $results]);
    }

    /**
     * Get document statistics for current user.
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->documentService->getStatistics();

        return response()->json(['data' => $stats]);
    }

    /**
     * List document templates.
     */
    public function templates(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $templates = $this->documentService->listTemplates($type);

        return response()->json(['data' => $templates]);
    }
}
