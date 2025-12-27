<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CollaborativeDocument;

use App\Application\Services\CollaborativeDocument\DocumentSyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DocumentSyncController extends Controller
{
    public function __construct(
        private DocumentSyncService $syncService,
    ) {}

    /**
     * Get the current document state (Y.js state for initial sync).
     */
    public function getState(int $id): JsonResponse
    {
        try {
            $state = $this->syncService->getDocumentState($id);

            return response()->json(['data' => $state]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Process a Y.js update from a client.
     */
    public function sync(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'update' => 'required|string', // Base64 encoded Y.js update
        ]);

        try {
            $result = $this->syncService->processUpdate(
                documentId: $id,
                yjsUpdate: $validated['update'],
            );

            return response()->json(['data' => $result]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Join a collaboration session.
     */
    public function join(int $id): JsonResponse
    {
        try {
            $result = $this->syncService->joinSession($id);

            return response()->json(['data' => $result]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Leave a collaboration session.
     */
    public function leave(int $id): JsonResponse
    {
        try {
            $result = $this->syncService->leaveSession($id);

            return response()->json(['data' => $result]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update cursor position.
     */
    public function updateCursor(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'line' => 'required|integer|min:0',
            'column' => 'required|integer|min:0',
            'selection' => 'nullable|array',
            'selection.start.line' => 'required_with:selection|integer|min:0',
            'selection.start.column' => 'required_with:selection|integer|min:0',
            'selection.end.line' => 'required_with:selection|integer|min:0',
            'selection.end.column' => 'required_with:selection|integer|min:0',
        ]);

        try {
            $result = $this->syncService->updateCursor($id, $validated);

            return response()->json(['data' => $result]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
