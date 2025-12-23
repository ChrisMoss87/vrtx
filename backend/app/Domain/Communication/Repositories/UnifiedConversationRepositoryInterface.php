<?php

declare(strict_types=1);

namespace App\Domain\Communication\Repositories;

use App\Domain\Communication\Entities\UnifiedConversation;
use App\Domain\Communication\Entities\UnifiedMessage;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\RecordContext;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface UnifiedConversationRepositoryInterface
{
    /**
     * Find a conversation by ID.
     */
    public function findById(int $id): ?UnifiedConversation;

    /**
     * Find a conversation by its source ID and channel.
     */
    public function findBySourceId(ChannelType $channel, string $sourceId): ?UnifiedConversation;

    /**
     * List conversations with filters and pagination.
     */
    public function list(array $filters, int $perPage, int $page): PaginatedResult;

    /**
     * Get conversations linked to a specific CRM record.
     */
    public function getByRecordContext(RecordContext $context): array;

    /**
     * Get conversations assigned to a user.
     */
    public function getByAssignee(int $userId, array $filters = []): array;

    /**
     * Save a conversation.
     */
    public function save(UnifiedConversation $conversation): UnifiedConversation;

    /**
     * Delete a conversation.
     */
    public function delete(int $id): bool;

    /**
     * Get messages for a conversation.
     */
    public function getMessages(int $conversationId, int $limit = 50, int $offset = 0): array;

    /**
     * Save a message.
     */
    public function saveMessage(UnifiedMessage $message): UnifiedMessage;

    /**
     * Find a message by ID.
     */
    public function findMessageById(int $id): ?UnifiedMessage;

    /**
     * Find a message by its source ID.
     */
    public function findMessageBySourceId(ChannelType $channel, string $sourceId): ?UnifiedMessage;

    /**
     * Get inbox statistics.
     */
    public function getStats(array $filters = []): array;

    /**
     * Get conversation count by status.
     */
    public function getCountByStatus(array $filters = []): array;

    /**
     * Get unread conversation count for a user.
     */
    public function getUnreadCount(?int $userId = null): int;
}
