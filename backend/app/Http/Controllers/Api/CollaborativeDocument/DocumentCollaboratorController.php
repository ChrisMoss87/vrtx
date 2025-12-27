<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CollaborativeDocument;

use App\Application\Services\CollaborativeDocument\DocumentSharingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DocumentCollaboratorController extends Controller
{
    public function __construct(
        private DocumentSharingService $sharingService,
    ) {}

    /**
     * List collaborators for a document.
     */
    public function index(int $documentId): JsonResponse
    {
        try {
            $collaborators = $this->sharingService->listCollaborators($documentId);

            return response()->json(['data' => $collaborators]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get active collaborators (currently viewing).
     */
    public function active(int $documentId): JsonResponse
    {
        try {
            $collaborators = $this->sharingService->getActiveCollaborators($documentId);

            return response()->json(['data' => $collaborators]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Add a collaborator to a document.
     */
    public function store(Request $request, int $documentId): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'permission' => 'required|string|in:view,comment,edit',
        ]);

        try {
            $collaborator = $this->sharingService->addCollaborator(
                documentId: $documentId,
                userId: $validated['user_id'],
                permission: $validated['permission'],
            );

            return response()->json([
                'message' => 'Collaborator added successfully',
                'data' => $collaborator,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update a collaborator's permission.
     */
    public function update(Request $request, int $documentId, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'permission' => 'required|string|in:view,comment,edit',
        ]);

        try {
            $collaborator = $this->sharingService->updateCollaboratorPermission(
                documentId: $documentId,
                userId: $userId,
                permission: $validated['permission'],
            );

            return response()->json([
                'message' => 'Permission updated successfully',
                'data' => $collaborator,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove a collaborator from a document.
     */
    public function destroy(int $documentId, int $userId): JsonResponse
    {
        try {
            $this->sharingService->removeCollaborator($documentId, $userId);

            return response()->json(['message' => 'Collaborator removed successfully']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get link sharing status.
     */
    public function getLinkSharing(int $documentId): JsonResponse
    {
        try {
            $status = $this->sharingService->getLinkSharingStatus($documentId);

            return response()->json(['data' => $status]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Enable link sharing.
     */
    public function enableLinkSharing(Request $request, int $documentId): JsonResponse
    {
        $validated = $request->validate([
            'permission' => 'required|string|in:view,comment,edit',
            'password' => 'nullable|string|min:4',
            'expires_at' => 'nullable|date|after:now',
            'allow_download' => 'sometimes|boolean',
            'require_email' => 'sometimes|boolean',
        ]);

        try {
            $result = $this->sharingService->enableLinkSharing(
                documentId: $documentId,
                permission: $validated['permission'],
                password: $validated['password'] ?? null,
                expiresAt: $validated['expires_at'] ?? null,
                allowDownload: $validated['allow_download'] ?? true,
                requireEmail: $validated['require_email'] ?? false,
            );

            return response()->json([
                'message' => 'Link sharing enabled',
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update link sharing settings.
     */
    public function updateLinkSharing(Request $request, int $documentId): JsonResponse
    {
        $validated = $request->validate([
            'permission' => 'nullable|string|in:view,comment,edit',
            'password' => 'nullable|string|min:4',
            'expires_at' => 'nullable|date|after:now',
            'allow_download' => 'nullable|boolean',
            'require_email' => 'nullable|boolean',
        ]);

        try {
            $result = $this->sharingService->updateLinkSharing(
                documentId: $documentId,
                permission: $validated['permission'] ?? null,
                password: $validated['password'] ?? null,
                expiresAt: $validated['expires_at'] ?? null,
                allowDownload: $validated['allow_download'] ?? null,
                requireEmail: $validated['require_email'] ?? null,
            );

            return response()->json([
                'message' => 'Link sharing updated',
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Disable link sharing.
     */
    public function disableLinkSharing(int $documentId): JsonResponse
    {
        try {
            $this->sharingService->disableLinkSharing($documentId);

            return response()->json(['message' => 'Link sharing disabled']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Regenerate share link token.
     */
    public function regenerateToken(int $documentId): JsonResponse
    {
        try {
            $result = $this->sharingService->regenerateShareToken($documentId);

            return response()->json([
                'message' => 'Share link regenerated',
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Access document via share link (public endpoint).
     */
    public function accessViaLink(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'nullable|string',
        ]);

        try {
            $result = $this->sharingService->accessViaShareLink(
                token: $token,
                password: $validated['password'] ?? null,
            );

            return response()->json(['data' => $result]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
