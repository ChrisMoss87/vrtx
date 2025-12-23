<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Repositories;

use App\Domain\Shared\ValueObjects\PaginatedResult;

interface InboxConversationRepositoryInterface
{
    // ==========================================
    // SHARED INBOX QUERY METHODS
    // ==========================================

    /**
     * List shared inboxes with filtering.
     */
    public function listInboxes(array $filters = []): array;

    /**
     * Get a single shared inbox by ID.
     */
    public function getInbox(int $id): ?array;

    /**
     * Get inbox with statistics.
     */
    public function getInboxWithStats(int $id): array;

    /**
     * Get inbox members.
     */
    public function getInboxMembers(int $inboxId): array;

    // ==========================================
    // SHARED INBOX COMMAND METHODS
    // ==========================================

    /**
     * Create a shared inbox.
     */
    public function createInbox(array $data, int $creatorUserId): array;

    /**
     * Update a shared inbox.
     */
    public function updateInbox(int $id, array $data): array;

    /**
     * Delete a shared inbox.
     */
    public function deleteInbox(int $id): void;

    /**
     * Add member to inbox.
     */
    public function addInboxMember(int $inboxId, array $data): array;

    /**
     * Update inbox member.
     */
    public function updateInboxMember(int $memberId, array $data): array;

    /**
     * Remove member from inbox.
     */
    public function removeInboxMember(int $memberId): void;

    /**
     * Test inbox connection.
     */
    public function testInboxConnection(int $id): array;

    /**
     * Sync inbox emails.
     */
    public function syncInbox(int $id): array;

    // ==========================================
    // CONVERSATION QUERY METHODS
    // ==========================================

    /**
     * List conversations with filtering and pagination.
     */
    public function listConversations(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get a single conversation with messages.
     */
    public function getConversation(int $id): ?array;

    /**
     * Get conversation messages.
     */
    public function getConversationMessages(int $conversationId, int $perPage = 50, int $page = 1): PaginatedResult;

    /**
     * Get conversation count by status.
     */
    public function getConversationCounts(int $inboxId): array;

    /**
     * Get assigned conversations for a user.
     */
    public function getMyConversations(int $userId, array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    // ==========================================
    // CONVERSATION COMMAND METHODS
    // ==========================================

    /**
     * Create a new conversation.
     */
    public function createConversation(int $inboxId, array $data): array;

    /**
     * Update conversation.
     */
    public function updateConversation(int $id, array $data): array;

    /**
     * Assign conversation.
     */
    public function assignConversation(int $id, ?int $userId): array;

    /**
     * Change conversation status.
     */
    public function changeStatus(int $id, string $status): array;

    /**
     * Toggle star on conversation.
     */
    public function toggleStar(int $id): array;

    /**
     * Mark conversation as spam.
     */
    public function markAsSpam(int $id): array;

    /**
     * Add tag to conversation.
     */
    public function addTag(int $id, string $tag): array;

    /**
     * Remove tag from conversation.
     */
    public function removeTag(int $id, string $tag): array;

    /**
     * Merge conversations.
     */
    public function mergeConversations(int $targetId, array $sourceIds): array;

    // ==========================================
    // MESSAGE METHODS
    // ==========================================

    /**
     * Send a reply message.
     */
    public function sendReply(int $conversationId, array $data, int $sentByUserId, ?string $userName): array;

    /**
     * Add an internal note.
     */
    public function addNote(int $conversationId, array $data, int $sentByUserId, ?string $userName): array;

    /**
     * Mark messages as read.
     */
    public function markMessagesAsRead(int $conversationId): void;

    /**
     * Process incoming message.
     */
    public function processIncomingMessage(int $inboxId, array $data): array;

    // ==========================================
    // CANNED RESPONSE METHODS
    // ==========================================

    /**
     * List canned responses.
     */
    public function listCannedResponses(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get canned response by shortcut.
     */
    public function getCannedResponseByShortcut(string $shortcut, ?int $inboxId = null): ?array;

    /**
     * Create canned response.
     */
    public function createCannedResponse(array $data, int $createdByUserId): array;

    /**
     * Update canned response.
     */
    public function updateCannedResponse(int $id, array $data): array;

    /**
     * Delete canned response.
     */
    public function deleteCannedResponse(int $id): void;

    /**
     * Use canned response.
     */
    public function useCannedResponse(int $id, array $variables = []): string;

    // ==========================================
    // INBOX RULE METHODS
    // ==========================================

    /**
     * List inbox rules.
     */
    public function listRules(int $inboxId): array;

    /**
     * Create inbox rule.
     */
    public function createRule(int $inboxId, array $data, int $createdByUserId): array;

    /**
     * Update inbox rule.
     */
    public function updateRule(int $id, array $data): array;

    /**
     * Delete inbox rule.
     */
    public function deleteRule(int $id): void;

    /**
     * Reorder inbox rules.
     */
    public function reorderRules(int $inboxId, array $ruleIds): void;

    // ==========================================
    // ANALYTICS METHODS
    // ==========================================

    /**
     * Get inbox performance metrics.
     */
    public function getInboxMetrics(int $inboxId, string $period = 'week'): array;

    /**
     * Get agent performance.
     */
    public function getAgentPerformance(int $inboxId, string $period = 'week'): array;

    /**
     * Get tag distribution.
     */
    public function getTagDistribution(int $inboxId): array;
}
