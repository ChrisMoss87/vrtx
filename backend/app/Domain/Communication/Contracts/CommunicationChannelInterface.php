<?php

declare(strict_types=1);

namespace App\Domain\Communication\Contracts;

use App\Domain\Communication\Entities\UnifiedConversation;
use App\Domain\Communication\Entities\UnifiedMessage;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\RecordContext;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface CommunicationChannelInterface
{
    /**
     * Get the channel type this adapter handles.
     */
    public function getChannelType(): ChannelType;

    /**
     * Check if this channel is available and configured.
     */
    public function isAvailable(): bool;

    /**
     * Get conversations from this channel.
     */
    public function getConversations(array $filters = [], int $perPage = 20, int $page = 1): PaginatedResult;

    /**
     * Get a specific conversation by its source ID.
     */
    public function getConversation(string $sourceId): ?UnifiedConversation;

    /**
     * Get conversations linked to a specific CRM record.
     */
    public function getConversationsForRecord(RecordContext $context): array;

    /**
     * Get messages for a conversation.
     */
    public function getMessages(string $sourceConversationId, int $limit = 50): array;

    /**
     * Send a message through this channel.
     */
    public function sendMessage(SendMessageDTO $message): UnifiedMessage;

    /**
     * Convert a channel-specific conversation to unified format.
     */
    public function toUnifiedConversation(mixed $sourceConversation): UnifiedConversation;

    /**
     * Convert a channel-specific message to unified format.
     */
    public function toUnifiedMessage(mixed $sourceMessage): UnifiedMessage;

    /**
     * Sync conversations from this channel (for pull-based channels).
     */
    public function sync(?int $userId = null): int;
}
