<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CollaborativeDocument;

use App\Domain\CollaborativeDocument\Entities\DocumentComment;
use App\Domain\CollaborativeDocument\Repositories\CollaborativeDocumentRepositoryInterface;
use App\Domain\CollaborativeDocument\Repositories\DocumentCommentRepositoryInterface;
use App\Domain\CollaborativeDocument\Repositories\DocumentCollaboratorRepositoryInterface;
use App\Domain\CollaborativeDocument\Events\CommentAdded;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DocumentCommentController extends Controller
{
    public function __construct(
        private CollaborativeDocumentRepositoryInterface $documentRepository,
        private DocumentCommentRepositoryInterface $commentRepository,
        private DocumentCollaboratorRepositoryInterface $collaboratorRepository,
        private AuthContextInterface $authContext,
        private Dispatcher $eventDispatcher,
    ) {}

    /**
     * List comments for a document.
     */
    public function index(Request $request, int $documentId): JsonResponse
    {
        $includeResolved = $request->boolean('include_resolved', true);

        if (!$this->hasAccess($documentId)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $comments = $this->commentRepository->findThreadsWithUserDetails(
            documentId: $documentId,
            includeResolved: $includeResolved,
        );

        return response()->json(['data' => $comments]);
    }

    /**
     * Get a specific comment thread with replies.
     */
    public function show(int $documentId, int $commentId): JsonResponse
    {
        if (!$this->hasAccess($documentId)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $thread = $this->commentRepository->findThreadWithReplies($commentId);

        if (empty($thread)) {
            return response()->json(['error' => 'Comment not found'], 404);
        }

        return response()->json(['data' => $thread]);
    }

    /**
     * Create a new comment (or reply to a thread).
     */
    public function store(Request $request, int $documentId): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000',
            'thread_id' => 'nullable|integer|exists:document_comments,id',
            'selection_range' => 'nullable|array',
            'selection_range.start' => 'required_with:selection_range|array',
            'selection_range.start.line' => 'required_with:selection_range|integer|min:0',
            'selection_range.start.column' => 'required_with:selection_range|integer|min:0',
            'selection_range.end' => 'required_with:selection_range|array',
            'selection_range.end.line' => 'required_with:selection_range|integer|min:0',
            'selection_range.end.column' => 'required_with:selection_range|integer|min:0',
        ]);

        if (!$this->canComment($documentId)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $userId = $this->authContext->getUserId();

        $comment = DocumentComment::create(
            documentId: $documentId,
            userId: $userId,
            content: $validated['content'],
            threadId: $validated['thread_id'] ?? null,
            selectionRange: $validated['selection_range'] ?? null,
        );

        $saved = $this->commentRepository->save($comment);

        // Dispatch event
        $this->eventDispatcher->dispatch(new CommentAdded(
            documentId: $documentId,
            commentId: $saved->getId(),
            userId: $userId,
            isReply: isset($validated['thread_id']),
        ));

        $result = $this->commentRepository->findByIdAsArray($saved->getId());

        return response()->json([
            'message' => 'Comment added successfully',
            'data' => $result,
        ], 201);
    }

    /**
     * Update a comment.
     */
    public function update(Request $request, int $documentId, int $commentId): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $comment = $this->commentRepository->findById($commentId);

        if (!$comment) {
            return response()->json(['error' => 'Comment not found'], 404);
        }

        $userId = $this->authContext->getUserId();

        // Only the comment author can edit
        if ($comment->getUserId() !== $userId) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $comment = $comment->updateContent($validated['content']);
        $this->commentRepository->save($comment);

        return response()->json([
            'message' => 'Comment updated successfully',
            'data' => $this->commentRepository->findByIdAsArray($commentId),
        ]);
    }

    /**
     * Delete a comment.
     */
    public function destroy(int $documentId, int $commentId): JsonResponse
    {
        $comment = $this->commentRepository->findById($commentId);

        if (!$comment) {
            return response()->json(['error' => 'Comment not found'], 404);
        }

        $userId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        // Comment author or document owner can delete
        $canDelete = $comment->getUserId() === $userId
            || ($document && $document->getOwnerId() === $userId);

        if (!$canDelete) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $this->commentRepository->delete($commentId);

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    /**
     * Resolve a comment thread.
     */
    public function resolve(int $documentId, int $commentId): JsonResponse
    {
        $comment = $this->commentRepository->findById($commentId);

        if (!$comment) {
            return response()->json(['error' => 'Comment not found'], 404);
        }

        // Only thread starters can be resolved
        if ($comment->getThreadId() !== null) {
            return response()->json(['error' => 'Only thread starters can be resolved'], 422);
        }

        if (!$this->canComment($documentId)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $userId = $this->authContext->getUserId();
        $comment = $comment->resolve($userId);
        $this->commentRepository->save($comment);

        return response()->json([
            'message' => 'Comment resolved',
            'data' => $this->commentRepository->findByIdAsArray($commentId),
        ]);
    }

    /**
     * Reopen a resolved comment thread.
     */
    public function reopen(int $documentId, int $commentId): JsonResponse
    {
        $comment = $this->commentRepository->findById($commentId);

        if (!$comment) {
            return response()->json(['error' => 'Comment not found'], 404);
        }

        if (!$this->canComment($documentId)) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $comment = $comment->reopen();
        $this->commentRepository->save($comment);

        return response()->json([
            'message' => 'Comment reopened',
            'data' => $this->commentRepository->findByIdAsArray($commentId),
        ]);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function hasAccess(int $documentId): bool
    {
        $userId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            return false;
        }

        if ($document->getOwnerId() === $userId) {
            return true;
        }

        return $this->collaboratorRepository->hasAccess($documentId, $userId);
    }

    private function canComment(int $documentId): bool
    {
        $userId = $this->authContext->getUserId();
        $document = $this->documentRepository->findById($documentId);

        if (!$document) {
            return false;
        }

        if ($document->getOwnerId() === $userId) {
            return true;
        }

        $permission = $this->collaboratorRepository->getPermission($documentId, $userId);
        return $permission?->canComment() ?? false;
    }
}
