<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CollaborativeDocument;

use App\Application\Services\CollaborativeDocument\DocumentSyncService;
use App\Domain\CollaborativeDocument\Repositories\DocumentVersionRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DocumentVersionController extends Controller
{
    public function __construct(
        private DocumentSyncService $syncService,
        private DocumentVersionRepositoryInterface $versionRepository,
        private AuthContextInterface $authContext,
    ) {}

    /**
     * List versions for a document.
     */
    public function index(Request $request, int $documentId): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        $page = max((int) $request->input('page', 1), 1);
        $includeAutoSaves = $request->boolean('include_auto_saves', false);

        $result = $this->versionRepository->listByDocument(
            documentId: $documentId,
            perPage: $perPage,
            page: $page,
            includeAutoSaves: $includeAutoSaves,
        );

        return response()->json($result->toArray());
    }

    /**
     * Get a specific version.
     */
    public function show(int $documentId, int $versionNumber): JsonResponse
    {
        $version = $this->versionRepository->findByDocumentAndVersion($documentId, $versionNumber);

        if (!$version) {
            return response()->json(['error' => 'Version not found'], 404);
        }

        return response()->json([
            'data' => $this->versionRepository->findByIdAsArray($version->getId()),
        ]);
    }

    /**
     * Create a named version (save point).
     */
    public function store(Request $request, int $documentId): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        try {
            $version = $this->syncService->createNamedVersion(
                documentId: $documentId,
                label: $validated['label'],
            );

            return response()->json([
                'message' => 'Version created successfully',
                'data' => $version,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Restore document to a specific version.
     */
    public function restore(int $documentId, int $versionNumber): JsonResponse
    {
        try {
            $document = $this->syncService->restoreVersion(
                documentId: $documentId,
                versionNumber: $versionNumber,
            );

            return response()->json([
                'message' => 'Document restored to version ' . $versionNumber,
                'data' => $document,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Compare two versions.
     */
    public function compare(Request $request, int $documentId): JsonResponse
    {
        $validated = $request->validate([
            'from_version' => 'required|integer|min:1',
            'to_version' => 'required|integer|min:1|different:from_version',
        ]);

        $fromVersion = $this->versionRepository->findByDocumentAndVersion(
            $documentId,
            $validated['from_version']
        );

        $toVersion = $this->versionRepository->findByDocumentAndVersion(
            $documentId,
            $validated['to_version']
        );

        if (!$fromVersion || !$toVersion) {
            return response()->json(['error' => 'One or both versions not found'], 404);
        }

        return response()->json([
            'data' => [
                'from' => $this->versionRepository->findByIdAsArray($fromVersion->getId()),
                'to' => $this->versionRepository->findByIdAsArray($toVersion->getId()),
                // Frontend will handle the actual diff visualization
            ],
        ]);
    }
}
