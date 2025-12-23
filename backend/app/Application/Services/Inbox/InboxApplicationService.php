<?php

declare(strict_types=1);

namespace App\Application\Services\Inbox;

use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class InboxApplicationService
{
    public function __construct(
        private InboxConversationRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // ==========================================
    // SHARED INBOX QUERY USE CASES
    // ==========================================

    /**
     * List shared inboxes the user has access to.
     */
    public function listInboxes(array $filters = []): array
    {
        return $this->repository->listInboxes($filters);
    }

    /**
     * Get a single shared inbox.
     */
    public function getInbox(int $id): ?array
    {
        return $this->repository->getInbox($id);
    }

    /**
     * Get inbox with statistics.
     */
    public function getInboxWithStats(int $id): array
    {
        return $this->repository->getInboxWithStats($id);
    }

    /**
     * Get inbox members.
     */
    public function getInboxMembers(int $inboxId): array
    {
        return $this->repository->getInboxMembers($inboxId);
    }

    // ==========================================
    // SHARED INBOX COMMAND USE CASES
    // ==========================================

    /**
     * Create a shared inbox.
     */
    public function createInbox(array $data): array
    {
        $userId = $this->authContext->userId();
        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to create inbox');
        }

        return $this->repository->createInbox($data, $userId);
    }

    /**
     * Update a shared inbox.
     */
    public function updateInbox(int $id, array $data): array
    {
        return $this->repository->updateInbox($id, $data);
    }

    /**
     * Delete a shared inbox.
     */
    public function deleteInbox(int $id): void
    {
        $this->repository->deleteInbox($id);
    }

    /**
     * Add member to inbox.
     */
    public function addInboxMember(int $inboxId, array $data): array
    {
        return $this->repository->addInboxMember($inboxId, $data);
    }

    /**
     * Update inbox member.
     */
    public function updateInboxMember(int $memberId, array $data): array
    {
        return $this->repository->updateInboxMember($memberId, $data);
    }

    /**
     * Remove member from inbox.
     */
    public function removeInboxMember(int $memberId): void
    {
        $this->repository->removeInboxMember($memberId);
    }

    /**
     * Test inbox connection.
     */
    public function testInboxConnection(int $id): array
    {
        return $this->repository->testInboxConnection($id);
    }

    /**
     * Sync inbox emails.
     */
    public function syncInbox(int $id): array
    {
        return $this->repository->syncInbox($id);
    }

    // ==========================================
    // CONVERSATION QUERY USE CASES
    // ==========================================

    /**
     * List conversations with filtering and pagination.
     */
    public function listConversations(array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->listConversations($filters, $perPage, $page);
    }

    /**
     * Get a single conversation with messages.
     */
    public function getConversation(int $id): ?array
    {
        return $this->repository->getConversation($id);
    }

    /**
     * Get conversation messages.
     */
    public function getConversationMessages(int $conversationId, int $perPage = 50): PaginatedResult
    {
        return $this->repository->getConversationMessages($conversationId, $perPage);
    }

    /**
     * Get conversation count by status.
     */
    public function getConversationCounts(int $inboxId): array
    {
        return $this->repository->getConversationCounts($inboxId);
    }

    /**
     * Get my assigned conversations.
     */
    public function getMyConversations(int $userId, array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->getMyConversations($userId, $filters, $perPage, $page);
    }

    // ==========================================
    // CONVERSATION COMMAND USE CASES
    // ==========================================

    /**
     * Create a new conversation.
     */
    public function createConversation(int $inboxId, array $data): array
    {
        return $this->repository->createConversation($inboxId, $data);
    }

    /**
     * Update conversation.
     */
    public function updateConversation(int $id, array $data): array
    {
        return $this->repository->updateConversation($id, $data);
    }

    /**
     * Assign conversation.
     */
    public function assignConversation(int $id, ?int $userId): array
    {
        return $this->repository->assignConversation($id, $userId);
    }

    /**
     * Change conversation status.
     */
    public function changeStatus(int $id, string $status): array
    {
        return $this->repository->changeStatus($id, $status);
    }

    /**
     * Toggle star on conversation.
     */
    public function toggleStar(int $id): array
    {
        return $this->repository->toggleStar($id);
    }

    /**
     * Mark conversation as spam.
     */
    public function markAsSpam(int $id): array
    {
        return $this->repository->markAsSpam($id);
    }

    /**
     * Add tag to conversation.
     */
    public function addTag(int $id, string $tag): array
    {
        return $this->repository->addTag($id, $tag);
    }

    /**
     * Remove tag from conversation.
     */
    public function removeTag(int $id, string $tag): array
    {
        return $this->repository->removeTag($id, $tag);
    }

    /**
     * Merge conversations.
     */
    public function mergeConversations(int $targetId, array $sourceIds): array
    {
        return $this->repository->mergeConversations($targetId, $sourceIds);
    }

    // ==========================================
    // MESSAGE USE CASES
    // ==========================================

    /**
     * Send a reply message.
     */
    public function sendReply(int $conversationId, array $data): array
    {
        $userId = $this->authContext->userId();
        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to send reply');
        }

        $userName = $this->authContext->userName();

        return $this->repository->sendReply($conversationId, $data, $userId, $userName);
    }

    /**
     * Add an internal note.
     */
    public function addNote(int $conversationId, array $data): array
    {
        $userId = $this->authContext->userId();
        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to add note');
        }

        $userName = $this->authContext->userName();

        return $this->repository->addNote($conversationId, $data, $userId, $userName);
    }

    /**
     * Mark messages as read.
     */
    public function markMessagesAsRead(int $conversationId): void
    {
        $this->repository->markMessagesAsRead($conversationId);
    }

    /**
     * Process incoming message.
     */
    public function processIncomingMessage(int $inboxId, array $data): array
    {
        return $this->repository->processIncomingMessage($inboxId, $data);
    }

    // ==========================================
    // CANNED RESPONSE USE CASES
    // ==========================================

    /**
     * List canned responses.
     */
    public function listCannedResponses(array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->listCannedResponses($filters, $perPage, $page);
    }

    /**
     * Get canned response by shortcut.
     */
    public function getCannedResponseByShortcut(string $shortcut, ?int $inboxId = null): ?array
    {
        return $this->repository->getCannedResponseByShortcut($shortcut, $inboxId);
    }

    /**
     * Create canned response.
     */
    public function createCannedResponse(array $data): array
    {
        $userId = $this->authContext->userId();
        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to create canned response');
        }

        return $this->repository->createCannedResponse($data, $userId);
    }

    /**
     * Update canned response.
     */
    public function updateCannedResponse(int $id, array $data): array
    {
        return $this->repository->updateCannedResponse($id, $data);
    }

    /**
     * Delete canned response.
     */
    public function deleteCannedResponse(int $id): void
    {
        $this->repository->deleteCannedResponse($id);
    }

    /**
     * Use canned response.
     */
    public function useCannedResponse(int $id, array $variables = []): string
    {
        return $this->repository->useCannedResponse($id, $variables);
    }

    // ==========================================
    // INBOX RULE USE CASES
    // ==========================================

    /**
     * List inbox rules.
     */
    public function listRules(int $inboxId): array
    {
        return $this->repository->listRules($inboxId);
    }

    /**
     * Create inbox rule.
     */
    public function createRule(int $inboxId, array $data): array
    {
        $userId = $this->authContext->userId();
        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to create rule');
        }

        return $this->repository->createRule($inboxId, $data, $userId);
    }

    /**
     * Update inbox rule.
     */
    public function updateRule(int $id, array $data): array
    {
        return $this->repository->updateRule($id, $data);
    }

    /**
     * Delete inbox rule.
     */
    public function deleteRule(int $id): void
    {
        $this->repository->deleteRule($id);
    }

    /**
     * Reorder inbox rules.
     */
    public function reorderRules(int $inboxId, array $ruleIds): void
    {
        $this->repository->reorderRules($inboxId, $ruleIds);
    }

    // ==========================================
    // ANALYTICS USE CASES
    // ==========================================

    /**
     * Get inbox performance metrics.
     */
    public function getInboxMetrics(int $inboxId, string $period = 'week'): array
    {
        return $this->repository->getInboxMetrics($inboxId, $period);
    }

    /**
     * Get agent performance.
     */
    public function getAgentPerformance(int $inboxId, string $period = 'week'): array
    {
        return $this->repository->getAgentPerformance($inboxId, $period);
    }

    /**
     * Get tag distribution.
     */
    public function getTagDistribution(int $inboxId): array
    {
        return $this->repository->getTagDistribution($inboxId);
    }
}
