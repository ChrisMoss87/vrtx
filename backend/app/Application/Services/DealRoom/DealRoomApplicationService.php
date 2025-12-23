<?php

declare(strict_types=1);

namespace App\Application\Services\DealRoom;

use App\Domain\DealRoom\Repositories\DealRoomRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DealRoomApplicationService
{
    public function __construct(
        private DealRoomRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - DEAL ROOMS
    // =========================================================================

    /**
     * List deal rooms with filtering and pagination.
     */
    public function listDealRooms(array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $with = ['creator:id,name,email', 'dealRecord'];

        return $this->repository->listDealRooms(
            $filters,
            $page,
            $perPage,
            $sortBy,
            $sortDir,
            $with
        );
    }

    /**
     * Get a deal room by ID.
     */
    public function getDealRoom(int $id): ?array
    {
        return $this->repository->findById($id, [
            'creator:id,name,email',
            'dealRecord',
            'members.user:id,name,email',
            'actionItems',
            'documents',
        ]);
    }

    /**
     * Get a deal room by slug (for external access).
     */
    public function getDealRoomBySlug(string $slug): ?array
    {
        return $this->repository->findBySlug($slug, [
            'members.user:id,name,email',
            'actionItems',
            'documents' => function ($q) {
                $q->visibleToExternal();
            },
        ]);
    }

    /**
     * Get deal rooms for a user.
     */
    public function getUserDealRooms(int $userId): array
    {
        return $this->repository->findByUserId($userId, [
            'dealRecord',
            'members',
        ]);
    }

    /**
     * Get deal room with full activity timeline.
     */
    public function getDealRoomWithTimeline(int $id, int $limit = 50): array
    {
        $room = $this->getDealRoom($id);

        if (!$room) {
            return [];
        }

        $activities = $this->repository->getActivityFeed($id, $limit);

        return [
            'room' => $room,
            'activities' => $activities,
        ];
    }

    /**
     * Get deal room analytics/engagement data.
     */
    public function getDealRoomEngagement(int $roomId): array
    {
        return $this->repository->getEngagementData($roomId);
    }

    // =========================================================================
    // COMMAND USE CASES - DEAL ROOMS
    // =========================================================================

    /**
     * Create a new deal room.
     */
    public function createDealRoom(array $data): array
    {
        $data['created_by'] = $this->authContext->userId();

        return $this->repository->createDealRoom($data);
    }

    /**
     * Update a deal room.
     */
    public function updateDealRoom(int $id, array $data): array
    {
        return $this->repository->updateDealRoom($id, $data);
    }

    /**
     * Update deal room status.
     */
    public function updateStatus(int $id, string $status): array
    {
        $validStatuses = ['active', 'won', 'lost', 'archived'];

        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException('Invalid status');
        }

        $room = $this->repository->updateDealRoom($id, ['status' => $status]);

        $this->repository->logActivity($id, 'status_changed', null, ['status' => $status]);

        return $room;
    }

    /**
     * Archive a deal room.
     */
    public function archiveDealRoom(int $id): array
    {
        return $this->updateStatus($id, 'archived');
    }

    /**
     * Delete a deal room.
     */
    public function deleteDealRoom(int $id): bool
    {
        return $this->repository->delete($id);
    }

    // =========================================================================
    // MEMBER USE CASES
    // =========================================================================

    /**
     * List members for a deal room.
     */
    public function listMembers(int $roomId): array
    {
        return $this->repository->listMembers($roomId);
    }

    /**
     * Add an internal member (user).
     */
    public function addInternalMember(int $roomId, int $userId, string $role = 'team'): array
    {
        // Check if already a member
        if ($this->repository->isUserMember($roomId, $userId)) {
            throw new InvalidArgumentException('User is already a member');
        }

        return $this->repository->createMember($roomId, [
            'user_id' => $userId,
            'role' => $role,
        ]);
    }

    /**
     * Add an external member (by email).
     */
    public function addExternalMember(int $roomId, string $email, string $name, string $role = 'stakeholder'): array
    {
        // Check if already a member
        if ($this->repository->isEmailMember($roomId, $email)) {
            throw new InvalidArgumentException('Email is already a member');
        }

        $member = $this->repository->createMember($roomId, [
            'external_email' => $email,
            'external_name' => $name,
            'role' => $role,
        ]);

        // Generate access token (we need to update the member)
        if (isset($member['id'])) {
            $token = bin2hex(random_bytes(32));
            $member = $this->repository->updateMember($member['id'], [
                'access_token' => $token,
                'access_token_expires_at' => now()->addMonths(6),
            ]);
        }

        return $member;
    }

    /**
     * Update a member's role.
     */
    public function updateMemberRole(int $memberId, string $role): array
    {
        // Validate role (this should ideally be in a value object or enum)
        $validRoles = ['owner', 'team', 'stakeholder', 'viewer'];

        if (!in_array($role, $validRoles)) {
            throw new InvalidArgumentException('Invalid role');
        }

        return $this->repository->updateMember($memberId, ['role' => $role]);
    }

    /**
     * Remove a member.
     */
    public function removeMember(int $memberId): bool
    {
        return $this->repository->deleteMember($memberId);
    }

    /**
     * Regenerate access token for external member.
     */
    public function regenerateAccessToken(int $memberId): string
    {
        $member = $this->repository->findMemberById($memberId);

        if (!$member) {
            throw new InvalidArgumentException('Member not found');
        }

        // Check if external member (no user_id)
        if (!empty($member['user_id'])) {
            throw new InvalidArgumentException('Can only regenerate tokens for external members');
        }

        $token = bin2hex(random_bytes(32));

        $this->repository->updateMember($memberId, [
            'access_token' => $token,
            'access_token_expires_at' => now()->addMonths(6),
        ]);

        return $token;
    }

    /**
     * Validate access token and record access.
     */
    public function validateAndRecordAccess(string $token): ?array
    {
        $member = $this->repository->findMemberByAccessToken($token);

        if (!$member) {
            return null;
        }

        // Check if token is valid (not expired)
        if (isset($member['access_token_expires_at'])) {
            $expiresAt = new \DateTime($member['access_token_expires_at']);
            if ($expiresAt < new \DateTime()) {
                return null;
            }
        }

        // Record access
        $this->repository->recordMemberAccess($member['id']);

        return $member;
    }

    // =========================================================================
    // DOCUMENT USE CASES
    // =========================================================================

    /**
     * List documents for a deal room.
     */
    public function listDocuments(int $roomId, bool $externalOnly = false): array
    {
        return $this->repository->listDocuments($roomId, $externalOnly);
    }

    /**
     * Upload a document.
     */
    public function uploadDocument(int $roomId, array $data, $file): array
    {
        // Store the file
        $path = $file->store("deal-rooms/{$roomId}/documents", 'private');

        $documentData = [
            'name' => $data['name'] ?? $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'version' => 1,
            'description' => $data['description'] ?? null,
            'is_visible_to_external' => $data['is_visible_to_external'] ?? true,
            'uploaded_by' => $this->authContext->userId(),
        ];

        return $this->repository->createDocument($roomId, $documentData);
    }

    /**
     * Update document metadata.
     */
    public function updateDocument(int $documentId, array $data): array
    {
        return $this->repository->updateDocument($documentId, $data);
    }

    /**
     * Delete a document.
     */
    public function deleteDocument(int $documentId): bool
    {
        $document = $this->repository->findDocumentById($documentId);

        if (!$document) {
            return false;
        }

        // Delete the file
        if (!empty($document['file_path'])) {
            Storage::disk('private')->delete($document['file_path']);
        }

        return $this->repository->deleteDocument($documentId);
    }

    /**
     * Track document view.
     */
    public function trackDocumentView(int $documentId, int $memberId, int $timeSpentSeconds = 0): array
    {
        return $this->repository->recordDocumentView($documentId, $memberId, $timeSpentSeconds);
    }

    /**
     * Get document analytics.
     */
    public function getDocumentAnalytics(int $documentId): array
    {
        return $this->repository->getDocumentAnalytics($documentId);
    }

    // =========================================================================
    // ACTION ITEM USE CASES
    // =========================================================================

    /**
     * List action items for a deal room.
     */
    public function listActionItems(int $roomId): array
    {
        return $this->repository->listActionItems($roomId);
    }

    /**
     * Create an action item.
     */
    public function createActionItem(int $roomId, array $data): array
    {
        // Get max display order
        $maxOrder = $this->repository->getMaxActionItemOrder($roomId);

        $itemData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'assigned_party' => $data['assigned_party'] ?? 'seller',
            'due_date' => $data['due_date'] ?? null,
            'status' => 'pending',
            'display_order' => $maxOrder + 1,
            'created_by' => $this->authContext->userId(),
        ];

        return $this->repository->createActionItem($roomId, $itemData);
    }

    /**
     * Update an action item.
     */
    public function updateActionItem(int $itemId, array $data): array
    {
        return $this->repository->updateActionItem($itemId, $data);
    }

    /**
     * Complete an action item.
     */
    public function completeActionItem(int $itemId, int $memberId): array
    {
        return $this->repository->updateActionItem($itemId, [
            'status' => 'completed',
            'completed_by' => $memberId,
            'completed_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Reopen an action item.
     */
    public function reopenActionItem(int $itemId): array
    {
        return $this->repository->updateActionItem($itemId, [
            'status' => 'pending',
            'completed_by' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Reorder action items.
     */
    public function reorderActionItems(int $roomId, array $orderedIds): void
    {
        $this->repository->reorderActionItems($roomId, $orderedIds);
    }

    /**
     * Delete an action item.
     */
    public function deleteActionItem(int $itemId): bool
    {
        return $this->repository->deleteActionItem($itemId);
    }

    // =========================================================================
    // MESSAGE USE CASES
    // =========================================================================

    /**
     * List messages for a deal room.
     */
    public function listMessages(int $roomId, int $perPage = 50): PaginatedResult
    {
        $page = 1; // Default to first page
        return $this->repository->listMessages($roomId, $page, $perPage);
    }

    /**
     * Send a message.
     */
    public function sendMessage(int $roomId, int $memberId, string $content, ?array $attachments = null): array
    {
        return $this->repository->createMessage($roomId, [
            'member_id' => $memberId,
            'content' => $content,
            'attachments' => $attachments,
        ]);
    }

    /**
     * Delete a message.
     */
    public function deleteMessage(int $messageId): bool
    {
        return $this->repository->deleteMessage($messageId);
    }

    // =========================================================================
    // ACTIVITY USE CASES
    // =========================================================================

    /**
     * Get activity feed for a deal room.
     */
    public function getActivityFeed(int $roomId, int $limit = 100): array
    {
        return $this->repository->getActivityFeed($roomId, $limit);
    }

    /**
     * Log custom activity.
     */
    public function logActivity(int $roomId, string $type, ?int $memberId = null, array $data = []): array
    {
        return $this->repository->logActivity($roomId, $type, $memberId, $data);
    }
}
