<?php

declare(strict_types=1);

namespace App\Domain\DealRoom\Repositories;

use App\Domain\DealRoom\Entities\DealRoom;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface DealRoomRepositoryInterface
{
    // =========================================================================
    // DEAL ROOM QUERIES (DDD-compliant)
    // =========================================================================

    /**
     * Find a deal room entity by ID.
     *
     * @param int $id
     * @return DealRoom|null Deal room entity
     */
    public function findById(int $id): ?DealRoom;

    /**
     * Save a deal room entity.
     *
     * @param DealRoom $dealRoom
     * @return DealRoom Saved deal room entity
     */
    public function save(DealRoom $dealRoom): DealRoom;

    /**
     * Find a deal room by ID with optional relations (backward compatibility).
     *
     * @param int $id
     * @param array $with Relations to eager load
     * @return array|null Deal room data as array
     */
    public function findByIdAsArray(int $id, array $with = []): ?array;

    /**
     * Find a deal room by slug.
     *
     * @param string $slug
     * @param array $with Relations to eager load
     * @return array|null Deal room data as array
     */
    public function findBySlug(string $slug, array $with = []): ?array;

    /**
     * Find a deal room by deal record ID.
     *
     * @param int $dealId
     * @return array|null Deal room data as array
     */
    public function findByDealId(int $dealId): ?array;

    /**
     * List deal rooms with filtering and pagination.
     *
     * @param array $filters Filtering options (status, active, user_id, deal_record_id, created_by, search)
     * @param int $page Current page
     * @param int $perPage Items per page
     * @param string $sortBy Sort field
     * @param string $sortDir Sort direction
     * @param array $with Relations to eager load
     * @return PaginatedResult Paginated deal rooms as arrays
     */
    public function listDealRooms(
        array $filters = [],
        int $page = 1,
        int $perPage = 25,
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
        array $with = []
    ): PaginatedResult;

    /**
     * Get deal rooms for a specific user.
     *
     * @param int $userId
     * @param array $with Relations to eager load
     * @return array Array of deal room data
     */
    public function findByUserId(int $userId, array $with = []): array;

    /**
     * Find active deal rooms.
     *
     * @param array $with Relations to eager load
     * @return array Array of deal room data
     */
    public function findActive(array $with = []): array;

    /**
     * Get deal room engagement/analytics data.
     *
     * @param int $roomId
     * @return array Engagement metrics
     */
    public function getEngagementData(int $roomId): array;

    // =========================================================================
    // MEMBER QUERIES
    // =========================================================================

    /**
     * List members for a deal room.
     *
     * @param int $roomId
     * @return array Array of member data
     */
    public function listMembers(int $roomId): array;

    /**
     * Find a member by ID.
     *
     * @param int $memberId
     * @return array|null Member data as array
     */
    public function findMemberById(int $memberId): ?array;

    /**
     * Find a member by access token.
     *
     * @param string $token
     * @return array|null Member data as array
     */
    public function findMemberByAccessToken(string $token): ?array;

    /**
     * Check if a user is already a member.
     *
     * @param int $roomId
     * @param int $userId
     * @return bool
     */
    public function isUserMember(int $roomId, int $userId): bool;

    /**
     * Check if an email is already a member.
     *
     * @param int $roomId
     * @param string $email
     * @return bool
     */
    public function isEmailMember(int $roomId, string $email): bool;

    // =========================================================================
    // DOCUMENT QUERIES
    // =========================================================================

    /**
     * List documents for a deal room.
     *
     * @param int $roomId
     * @param bool $externalOnly Filter to only external-visible documents
     * @return array Array of document data
     */
    public function listDocuments(int $roomId, bool $externalOnly = false): array;

    /**
     * Find a document by ID.
     *
     * @param int $documentId
     * @return array|null Document data as array
     */
    public function findDocumentById(int $documentId): ?array;

    /**
     * Get document analytics.
     *
     * @param int $documentId
     * @return array Analytics data
     */
    public function getDocumentAnalytics(int $documentId): array;

    // =========================================================================
    // ACTION ITEM QUERIES
    // =========================================================================

    /**
     * List action items for a deal room.
     *
     * @param int $roomId
     * @return array Array of action item data
     */
    public function listActionItems(int $roomId): array;

    /**
     * Find an action item by ID.
     *
     * @param int $itemId
     * @return array|null Action item data as array
     */
    public function findActionItemById(int $itemId): ?array;

    /**
     * Get max display order for action items in a room.
     *
     * @param int $roomId
     * @return int Maximum display order
     */
    public function getMaxActionItemOrder(int $roomId): int;

    // =========================================================================
    // MESSAGE QUERIES
    // =========================================================================

    /**
     * List messages for a deal room with pagination.
     *
     * @param int $roomId
     * @param int $page Current page
     * @param int $perPage Items per page
     * @return PaginatedResult Paginated messages as arrays
     */
    public function listMessages(int $roomId, int $page = 1, int $perPage = 50): PaginatedResult;

    /**
     * Find a message by ID.
     *
     * @param int $messageId
     * @return array|null Message data as array
     */
    public function findMessageById(int $messageId): ?array;

    // =========================================================================
    // ACTIVITY QUERIES
    // =========================================================================

    /**
     * Get activity feed for a deal room.
     *
     * @param int $roomId
     * @param int $limit Maximum number of activities
     * @return array Array of activity data
     */
    public function getActivityFeed(int $roomId, int $limit = 100): array;

    /**
     * Get activity count for date range.
     *
     * @param int $roomId
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return int Activity count
     */
    public function getActivityCount(int $roomId, string $startDate, string $endDate): int;

    // =========================================================================
    // COMMAND METHODS
    // =========================================================================

    /**
     * Create a new deal room.
     *
     * @param array $data Deal room data
     * @return array Created deal room data
     */
    public function createDealRoom(array $data): array;

    /**
     * Update a deal room.
     *
     * @param int $id
     * @param array $data Update data
     * @return array Updated deal room data
     */
    public function updateDealRoom(int $id, array $data): array;

    /**
     * Delete a deal room.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Create a member.
     *
     * @param int $roomId
     * @param array $data Member data
     * @return array Created member data
     */
    public function createMember(int $roomId, array $data): array;

    /**
     * Update a member.
     *
     * @param int $memberId
     * @param array $data Update data
     * @return array Updated member data
     */
    public function updateMember(int $memberId, array $data): array;

    /**
     * Delete a member.
     *
     * @param int $memberId
     * @return bool
     */
    public function deleteMember(int $memberId): bool;

    /**
     * Record member access.
     *
     * @param int $memberId
     * @return void
     */
    public function recordMemberAccess(int $memberId): void;

    /**
     * Create a document.
     *
     * @param int $roomId
     * @param array $data Document data
     * @return array Created document data
     */
    public function createDocument(int $roomId, array $data): array;

    /**
     * Update a document.
     *
     * @param int $documentId
     * @param array $data Update data
     * @return array Updated document data
     */
    public function updateDocument(int $documentId, array $data): array;

    /**
     * Delete a document.
     *
     * @param int $documentId
     * @return bool
     */
    public function deleteDocument(int $documentId): bool;

    /**
     * Record a document view.
     *
     * @param int $documentId
     * @param int $memberId
     * @param int $timeSpentSeconds
     * @return array Created view data
     */
    public function recordDocumentView(int $documentId, int $memberId, int $timeSpentSeconds): array;

    /**
     * Create an action item.
     *
     * @param int $roomId
     * @param array $data Action item data
     * @return array Created action item data
     */
    public function createActionItem(int $roomId, array $data): array;

    /**
     * Update an action item.
     *
     * @param int $itemId
     * @param array $data Update data
     * @return array Updated action item data
     */
    public function updateActionItem(int $itemId, array $data): array;

    /**
     * Reorder action items.
     *
     * @param int $roomId
     * @param array $orderedIds Ordered array of item IDs
     * @return void
     */
    public function reorderActionItems(int $roomId, array $orderedIds): void;

    /**
     * Delete an action item.
     *
     * @param int $itemId
     * @return bool
     */
    public function deleteActionItem(int $itemId): bool;

    /**
     * Create a message.
     *
     * @param int $roomId
     * @param array $data Message data
     * @return array Created message data
     */
    public function createMessage(int $roomId, array $data): array;

    /**
     * Delete a message.
     *
     * @param int $messageId
     * @return bool
     */
    public function deleteMessage(int $messageId): bool;

    /**
     * Log an activity.
     *
     * @param int $roomId
     * @param string $type Activity type
     * @param int|null $memberId Member ID if applicable
     * @param array $data Activity data
     * @return array Created activity data
     */
    public function logActivity(int $roomId, string $type, ?int $memberId, array $data): array;
}
