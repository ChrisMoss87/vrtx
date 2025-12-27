<?php

declare(strict_types=1);

namespace App\Application\Services\Chat;

use App\Domain\Chat\Repositories\ChatConversationRepositoryInterface;
use App\Domain\Chat\Repositories\ChatWidgetRepositoryInterface;
use App\Domain\Chat\Repositories\ChatVisitorRepositoryInterface;
use App\Domain\Chat\Repositories\ChatAgentStatusRepositoryInterface;
use App\Domain\Chat\Repositories\ChatMessageRepositoryInterface;
use App\Domain\Chat\Repositories\ChatCannedResponseRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatApplicationService
{
    public function __construct(
        private ChatConversationRepositoryInterface $conversationRepository,
        private ChatWidgetRepositoryInterface $widgetRepository,
        private ChatVisitorRepositoryInterface $visitorRepository,
        private ChatAgentStatusRepositoryInterface $agentStatusRepository,
        private ChatMessageRepositoryInterface $messageRepository,
        private ChatCannedResponseRepositoryInterface $cannedResponseRepository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // WIDGET USE CASES
    // =========================================================================

    /**
     * List widgets
     */
    public function listWidgets(bool $activeOnly = false): Collection
    {
        return $this->widgetRepository->findAll($activeOnly);
    }

    /**
     * Get a widget by ID
     */
    public function getWidget(int $widgetId): ?array
    {
        return $this->widgetRepository->findById($widgetId);
    }

    /**
     * Get widget by key (for embed)
     */
    public function getWidgetByKey(string $key): ?array
    {
        return $this->widgetRepository->findByKey($key);
    }

    /**
     * Create a widget
     */
    public function createWidget(array $data): int
    {
        return $this->widgetRepository->create($data);
    }

    /**
     * Update a widget
     */
    public function updateWidget(int $widgetId, array $data): array
    {
        return $this->widgetRepository->update($widgetId, $data);
    }

    /**
     * Delete a widget
     */
    public function deleteWidget(int $widgetId): bool
    {
        return DB::transaction(function () use ($widgetId) {
            $this->messageRepository->deleteByWidgetId($widgetId);
            $this->conversationRepository->deleteByWidgetId($widgetId);
            $this->visitorRepository->deleteByWidgetId($widgetId);

            return $this->widgetRepository->delete($widgetId);
        });
    }

    /**
     * Get widget status (online/offline, active agents)
     */
    public function getWidgetStatus(int $widgetId): array
    {
        return $this->widgetRepository->getStatus($widgetId);
    }

    /**
     * Get embed code for a widget
     */
    public function getWidgetEmbedCode(int $widgetId): string
    {
        return $this->widgetRepository->getEmbedCode($widgetId);
    }

    // =========================================================================
    // VISITOR USE CASES
    // =========================================================================

    /**
     * Get or create a visitor
     */
    public function getOrCreateVisitor(int $widgetId, string $fingerprint, array $data = []): array
    {
        return $this->visitorRepository->firstOrCreate($widgetId, $fingerprint, $data);
    }

    /**
     * Identify a visitor
     */
    public function identifyVisitor(int $visitorId, string $email, ?string $name = null): array
    {
        return $this->visitorRepository->identify($visitorId, $email, $name);
    }

    /**
     * Record page view
     */
    public function recordPageView(int $visitorId, string $url, ?string $title = null): array
    {
        return $this->visitorRepository->recordPageView($visitorId, $url, $title);
    }

    /**
     * Get visitor by fingerprint
     */
    public function getVisitorByFingerprint(int $widgetId, string $fingerprint): ?array
    {
        return $this->visitorRepository->findByFingerprint($widgetId, $fingerprint);
    }

    /**
     * List online visitors for a widget
     */
    public function getOnlineVisitors(int $widgetId, int $minutesThreshold = 5): Collection
    {
        return $this->visitorRepository->findOnlineVisitors($widgetId, $minutesThreshold);
    }

    // =========================================================================
    // CONVERSATION QUERY USE CASES
    // =========================================================================

    /**
     * List conversations with filtering
     */
    public function listConversations(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        return $this->conversationRepository->listConversations($filters, $perPage, $page);
    }

    /**
     * Get a single conversation with messages
     */
    public function getConversation(int $conversationId): ?array
    {
        return $this->conversationRepository->findByIdWithRelations($conversationId, [
            'visitor.contact',
            'assignedAgent',
            'widget',
            'messages',
        ]);
    }

    /**
     * Get conversation for a visitor's active session
     */
    public function getActiveConversationForVisitor(int $visitorId): ?array
    {
        return $this->conversationRepository->findActiveConversationForVisitor($visitorId);
    }

    /**
     * Get unassigned conversations (queue)
     */
    public function getUnassignedConversations(int $limit = 50): array
    {
        return $this->conversationRepository->findUnassignedConversations($limit);
    }

    /**
     * Get conversations for current agent
     */
    public function getMyConversations(): array
    {
        $userId = $this->authContext->userId();

        if (!$userId) {
            return [];
        }

        return $this->conversationRepository->findMyConversations($userId);
    }

    /**
     * Get conversation statistics
     */
    public function getConversationStats(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->conversationRepository->getConversationStats($startDate, $endDate);
    }

    // =========================================================================
    // CONVERSATION COMMAND USE CASES
    // =========================================================================

    /**
     * Start a new conversation
     */
    public function startConversation(int $widgetId, int $visitorId, array $data = []): array
    {
        return DB::transaction(function () use ($widgetId, $visitorId, $data) {
            $conversation = $this->conversationRepository->create([
                'widget_id' => $widgetId,
                'visitor_id' => $visitorId,
                'status' => 'open',
                'priority' => $data['priority'] ?? 'normal',
                'department' => $data['department'] ?? null,
                'subject' => $data['subject'] ?? null,
            ]);

            // Auto-assign if routing rules exist
            $widget = $this->widgetRepository->findById($widgetId);
            $routingRules = $widget['routing_rules'] ?? null;
            if (is_string($routingRules)) {
                $routingRules = json_decode($routingRules, true);
            }

            $agent = $this->agentStatusRepository->findBestAvailableAgent(
                $routingRules,
                $data['department'] ?? null
            );

            if ($agent) {
                $conversation = $this->conversationRepository->assign($conversation['id'], $agent['user_id']);
            }

            // Add initial message if provided
            if (!empty($data['initial_message'])) {
                $this->messageRepository->create(
                    $conversation['id'],
                    $data['initial_message'],
                    'visitor'
                );
            }

            return $this->conversationRepository->findByIdWithRelations($conversation['id'], [
                'visitor',
                'assignedAgent',
            ]);
        });
    }

    /**
     * Send a message
     */
    public function sendMessage(
        int $conversationId,
        string $content,
        string $senderType,
        ?int $senderId = null,
        array $options = []
    ): array {
        $conversation = $this->conversationRepository->findByIdAsArray($conversationId);

        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        // Reopen if closed and visitor sends message
        if ($conversation['status'] === 'closed' && $senderType === 'visitor') {
            $this->conversationRepository->reopen($conversationId);
        }

        return $this->messageRepository->create($conversationId, $content, $senderType, $senderId, $options);
    }

    /**
     * Send visitor message (for widget)
     */
    public function sendVisitorMessage(int $conversationId, string $content): array
    {
        return $this->sendMessage($conversationId, $content, 'visitor');
    }

    /**
     * Send agent message
     */
    public function sendAgentMessage(int $conversationId, string $content, array $options = []): array
    {
        $userId = $this->authContext->userId();

        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to send agent messages');
        }

        $message = $this->sendMessage(
            $conversationId,
            $content,
            'agent',
            $userId,
            $options
        );

        // Update agent activity
        $this->agentStatusRepository->recordActivity($userId);

        return $message;
    }

    /**
     * Assign conversation to agent
     */
    public function assignConversation(int $conversationId, int $userId): array
    {
        return $this->conversationRepository->assign($conversationId, $userId);
    }

    /**
     * Unassign conversation
     */
    public function unassignConversation(int $conversationId): array
    {
        return $this->conversationRepository->unassign($conversationId);
    }

    /**
     * Transfer conversation to another agent
     */
    public function transferConversation(int $conversationId, int $newAgentId, ?string $note = null): array
    {
        $conversation = $this->conversationRepository->findByIdAsArray($conversationId);

        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        // Unassign from current agent
        $this->conversationRepository->unassign($conversationId);

        // Assign to new agent
        $this->conversationRepository->assign($conversationId, $newAgentId);

        // Add system message about transfer
        $newAgent = $this->agentStatusRepository->findByUserId($newAgentId);
        $agentName = $newAgent['user']['name'] ?? 'another agent';
        $transferMessage = "Conversation transferred to {$agentName}";
        if ($note) {
            $transferMessage .= ". Note: {$note}";
        }

        $this->messageRepository->create($conversationId, $transferMessage, 'system', null, ['is_internal' => true]);

        return $this->conversationRepository->findByIdWithRelations($conversationId, ['assignedAgent']);
    }

    /**
     * Close a conversation
     */
    public function closeConversation(int $conversationId): array
    {
        return $this->conversationRepository->close($conversationId);
    }

    /**
     * Reopen a conversation
     */
    public function reopenConversation(int $conversationId): array
    {
        return $this->conversationRepository->reopen($conversationId);
    }

    /**
     * Set conversation priority
     */
    public function setConversationPriority(int $conversationId, string $priority): array
    {
        return $this->conversationRepository->update($conversationId, ['priority' => $priority]);
    }

    /**
     * Add tags to conversation
     */
    public function addConversationTags(int $conversationId, array $tags): array
    {
        return $this->conversationRepository->addTags($conversationId, $tags);
    }

    /**
     * Rate conversation (from visitor)
     */
    public function rateConversation(int $conversationId, float $rating, ?string $comment = null): array
    {
        return $this->conversationRepository->rate($conversationId, $rating, $comment);
    }

    /**
     * Mark messages as read
     */
    public function markMessagesAsRead(int $conversationId, string $readerType): int
    {
        return $this->messageRepository->markAsRead($conversationId, $readerType);
    }

    // =========================================================================
    // AGENT STATUS USE CASES
    // =========================================================================

    /**
     * Get agent status
     */
    public function getAgentStatus(?int $userId = null): array
    {
        return $this->agentStatusRepository->getOrCreate($userId ?? $this->authContext->userId());
    }

    /**
     * Set agent online
     */
    public function setAgentOnline(?int $userId = null): array
    {
        return $this->agentStatusRepository->setStatus($userId ?? $this->authContext->userId(), 'online');
    }

    /**
     * Set agent away
     */
    public function setAgentAway(?int $userId = null): array
    {
        return $this->agentStatusRepository->setStatus($userId ?? $this->authContext->userId(), 'away');
    }

    /**
     * Set agent busy
     */
    public function setAgentBusy(?int $userId = null): array
    {
        return $this->agentStatusRepository->setStatus($userId ?? $this->authContext->userId(), 'busy');
    }

    /**
     * Set agent offline
     */
    public function setAgentOffline(?int $userId = null): array
    {
        return $this->agentStatusRepository->setStatus($userId ?? $this->authContext->userId(), 'offline');
    }

    /**
     * Update agent settings
     */
    public function updateAgentSettings(array $data, ?int $userId = null): array
    {
        return $this->agentStatusRepository->updateSettings($userId ?? $this->authContext->userId(), $data);
    }

    /**
     * Get all online agents
     */
    public function getOnlineAgents(): Collection
    {
        return $this->agentStatusRepository->findOnline();
    }

    /**
     * Get available agents for assignment
     */
    public function getAvailableAgents(?string $department = null): Collection
    {
        return $this->agentStatusRepository->findAvailable($department);
    }

    // =========================================================================
    // CANNED RESPONSE USE CASES
    // =========================================================================

    /**
     * List canned responses for current user
     */
    public function listCannedResponses(array $filters = []): Collection
    {
        $userId = $this->authContext->userId();

        if (!$userId) {
            return collect([]);
        }

        return $this->cannedResponseRepository->findForUser($userId, $filters);
    }

    /**
     * Get canned response by shortcut
     */
    public function getCannedResponseByShortcut(string $shortcut): ?array
    {
        $userId = $this->authContext->userId();

        if (!$userId) {
            return null;
        }

        return $this->cannedResponseRepository->findByShortcut($userId, $shortcut);
    }

    /**
     * Create canned response
     */
    public function createCannedResponse(array $data): int
    {
        $userId = $this->authContext->userId();

        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to create canned responses');
        }

        return $this->cannedResponseRepository->create([
            'shortcut' => $data['shortcut'],
            'title' => $data['title'],
            'content' => $data['content'],
            'category' => $data['category'] ?? null,
            'is_global' => $data['is_global'] ?? false,
            'created_by' => $userId,
        ]);
    }

    /**
     * Update canned response
     */
    public function updateCannedResponse(int $responseId, array $data): array
    {
        return $this->cannedResponseRepository->update($responseId, $data);
    }

    /**
     * Delete canned response
     */
    public function deleteCannedResponse(int $responseId): bool
    {
        return $this->cannedResponseRepository->delete($responseId);
    }

    /**
     * Use canned response in conversation
     */
    public function useCannedResponse(int $conversationId, int $responseId, array $variables = []): array
    {
        $this->cannedResponseRepository->incrementUsage($responseId);

        $content = $this->cannedResponseRepository->renderContent($responseId, $variables);

        return $this->sendAgentMessage($conversationId, $content);
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get agent performance stats
     */
    public function getAgentPerformance(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        return $this->conversationRepository->getAgentPerformance($userId, $startDate, $endDate);
    }

    /**
     * Get hourly chat volume
     */
    public function getHourlyChatVolume(int $days = 7): array
    {
        return $this->conversationRepository->getHourlyChatVolume($days);
    }
}
