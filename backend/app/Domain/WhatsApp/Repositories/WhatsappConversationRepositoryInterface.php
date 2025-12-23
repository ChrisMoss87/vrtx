<?php

declare(strict_types=1);

namespace App\Domain\WhatsApp\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\WhatsApp\Entities\WhatsappConversation;

interface WhatsappConversationRepositoryInterface
{
    /**
     * Find conversation by ID.
     */
    public function findById(int $id): ?WhatsappConversation;

    /**
     * Find conversation by ID as array (for backward compatibility).
     */
    public function findByIdAsArray(int $id, array $with = []): ?array;

    /**
     * Find conversation by connection and contact WhatsApp ID.
     */
    public function findByConnectionAndContact(int $connectionId, string $contactWaId): ?array;

    /**
     * List conversations with filtering and pagination.
     */
    public function list(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get conversations assigned to a user.
     */
    public function findByAssignedUser(int $userId, array $filters = []): array;

    /**
     * Get unread conversations count for a user.
     */
    public function countUnreadByUser(int $userId): int;

    /**
     * Get conversations by module record.
     */
    public function findByModuleRecord(string $moduleApiName, int $moduleRecordId): array;

    /**
     * Save a conversation entity.
     */
    public function save(WhatsappConversation $entity): WhatsappConversation;

    /**
     * Create or get conversation for a contact.
     */
    public function getOrCreate(int $connectionId, string $contactWaId, string $contactPhone, ?string $contactName = null): array;

    /**
     * Update conversation.
     */
    public function update(int $id, array $data): ?array;

    /**
     * Assign conversation to a user.
     */
    public function assign(int $id, int $userId): bool;

    /**
     * Link conversation to a module record.
     */
    public function linkToRecord(int $id, string $moduleApiName, int $recordId): bool;

    /**
     * Mark conversation as read.
     */
    public function markAsRead(int $id): bool;

    /**
     * Close a conversation.
     */
    public function close(int $id): bool;

    /**
     * Reopen a conversation.
     */
    public function reopen(int $id): bool;

    /**
     * Increment unread count.
     */
    public function incrementUnread(int $id): bool;

    /**
     * Update last message timestamps.
     */
    public function updateTimestamps(int $id, array $timestamps): bool;

    /**
     * Get conversation statistics.
     */
    public function getStats(?int $connectionId = null, ?string $fromDate = null, ?string $toDate = null): array;

    /**
     * Count conversations by status.
     */
    public function countByStatus(?int $connectionId = null): array;

    /**
     * Check if conversation has unresolved conversations.
     */
    public function hasUnresolvedByConnection(int $connectionId): bool;
}
