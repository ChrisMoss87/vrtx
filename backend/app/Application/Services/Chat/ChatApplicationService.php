<?php

declare(strict_types=1);

namespace App\Application\Services\Chat;

use App\Domain\Chat\Repositories\ChatConversationRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Models\ChatAgentStatus;
use App\Models\ChatCannedResponse;
use App\Models\ChatMessage;
use App\Models\ChatVisitor;
use App\Models\ChatWidget;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatApplicationService
{
    public function __construct(
        private ChatConversationRepositoryInterface $conversationRepository,
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
        $query = ChatWidget::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a widget by ID
     */
    public function getWidget(int $widgetId): ?ChatWidget
    {
        return ChatWidget::find($widgetId);
    }

    /**
     * Get widget by key (for embed)
     */
    public function getWidgetByKey(string $key): ?ChatWidget
    {
        return ChatWidget::where('widget_key', $key)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Create a widget
     */
    public function createWidget(array $data): ChatWidget
    {
        $widget = new ChatWidget();

        return ChatWidget::create([
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
            'settings' => array_merge($widget->getDefaultSettings(), $data['settings'] ?? []),
            'styling' => array_merge($widget->getDefaultStyling(), $data['styling'] ?? []),
            'routing_rules' => $data['routing_rules'] ?? null,
            'business_hours' => $data['business_hours'] ?? null,
            'allowed_domains' => $data['allowed_domains'] ?? null,
        ]);
    }

    /**
     * Update a widget
     */
    public function updateWidget(int $widgetId, array $data): ChatWidget
    {
        $widget = ChatWidget::findOrFail($widgetId);

        $widget->update([
            'name' => $data['name'] ?? $widget->name,
            'is_active' => $data['is_active'] ?? $widget->is_active,
            'settings' => $data['settings'] ?? $widget->settings,
            'styling' => $data['styling'] ?? $widget->styling,
            'routing_rules' => $data['routing_rules'] ?? $widget->routing_rules,
            'business_hours' => $data['business_hours'] ?? $widget->business_hours,
            'allowed_domains' => $data['allowed_domains'] ?? $widget->allowed_domains,
        ]);

        return $widget->fresh();
    }

    /**
     * Delete a widget
     */
    public function deleteWidget(int $widgetId): bool
    {
        return DB::transaction(function () use ($widgetId) {
            // Delete related data
            ChatMessage::whereHas('conversation', fn($q) => $q->where('widget_id', $widgetId))->delete();
            ChatConversation::where('widget_id', $widgetId)->delete();
            ChatVisitor::where('widget_id', $widgetId)->delete();

            return ChatWidget::findOrFail($widgetId)->delete();
        });
    }

    /**
     * Get widget status (online/offline, active agents)
     */
    public function getWidgetStatus(int $widgetId): array
    {
        $widget = ChatWidget::findOrFail($widgetId);

        $onlineAgents = ChatAgentStatus::online()->with(['user:id,name'])->get();
        $availableAgents = ChatAgentStatus::available()->count();

        return [
            'widget_active' => $widget->is_active,
            'is_online' => $widget->isOnline(),
            'online_agents' => $onlineAgents->count(),
            'available_agents' => $availableAgents,
            'agents' => $onlineAgents->map(fn($a) => [
                'user_id' => $a->user_id,
                'name' => $a->user->name,
                'status' => $a->status,
                'active_conversations' => $a->active_conversations,
            ])->toArray(),
        ];
    }

    /**
     * Get embed code for a widget
     */
    public function getWidgetEmbedCode(int $widgetId): string
    {
        $widget = ChatWidget::findOrFail($widgetId);
        return $widget->getEmbedCode();
    }

    // =========================================================================
    // VISITOR USE CASES
    // =========================================================================

    /**
     * Get or create a visitor
     */
    public function getOrCreateVisitor(int $widgetId, string $fingerprint, array $data = []): ChatVisitor
    {
        return ChatVisitor::firstOrCreate(
            [
                'widget_id' => $widgetId,
                'fingerprint' => $fingerprint,
            ],
            [
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,
                'referrer' => $data['referrer'] ?? null,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]
        );
    }

    /**
     * Identify a visitor
     */
    public function identifyVisitor(int $visitorId, string $email, ?string $name = null): ChatVisitor
    {
        $visitor = ChatVisitor::findOrFail($visitorId);
        $visitor->identify($email, $name);
        return $visitor->fresh(['contact']);
    }

    /**
     * Record page view
     */
    public function recordPageView(int $visitorId, string $url, ?string $title = null): ChatVisitor
    {
        $visitor = ChatVisitor::findOrFail($visitorId);
        $visitor->recordPageView($url, $title);
        return $visitor;
    }

    /**
     * Get visitor by fingerprint
     */
    public function getVisitorByFingerprint(int $widgetId, string $fingerprint): ?ChatVisitor
    {
        return ChatVisitor::where('widget_id', $widgetId)
            ->byFingerprint($fingerprint)
            ->first();
    }

    /**
     * List online visitors for a widget
     */
    public function getOnlineVisitors(int $widgetId, int $minutesThreshold = 5): Collection
    {
        return ChatVisitor::where('widget_id', $widgetId)
            ->where('last_seen_at', '>=', now()->subMinutes($minutesThreshold))
            ->with(['contact'])
            ->orderByDesc('last_seen_at')
            ->get();
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
    public function startConversation(int $widgetId, int $visitorId, array $data = []): ChatConversation
    {
        return DB::transaction(function () use ($widgetId, $visitorId, $data) {
            $conversation = ChatConversation::create([
                'widget_id' => $widgetId,
                'visitor_id' => $visitorId,
                'status' => ChatConversation::STATUS_OPEN,
                'priority' => $data['priority'] ?? ChatConversation::PRIORITY_NORMAL,
                'department' => $data['department'] ?? null,
                'subject' => $data['subject'] ?? null,
            ]);

            // Auto-assign if routing rules exist
            $widget = ChatWidget::find($widgetId);
            $agent = $this->findAvailableAgent($widget->routing_rules, $data['department'] ?? null);

            if ($agent) {
                $conversation->assign($agent->user_id);
            }

            // Add initial message if provided
            if (!empty($data['initial_message'])) {
                $conversation->addMessage(
                    $data['initial_message'],
                    ChatMessage::SENDER_VISITOR
                );
            }

            return $conversation->load(['visitor', 'assignedAgent']);
        });
    }

    /**
     * Send a message
     */
    public function sendMessage(int $conversationId, string $content, string $senderType, ?int $senderId = null, array $options = []): ChatMessage
    {
        $conversation = ChatConversation::findOrFail($conversationId);

        // Reopen if closed and visitor sends message
        if ($conversation->status === ChatConversation::STATUS_CLOSED && $senderType === ChatMessage::SENDER_VISITOR) {
            $conversation->reopen();
        }

        return $conversation->addMessage($content, $senderType, $senderId, $options);
    }

    /**
     * Send visitor message (for widget)
     */
    public function sendVisitorMessage(int $conversationId, string $content): ChatMessage
    {
        return $this->sendMessage($conversationId, $content, ChatMessage::SENDER_VISITOR);
    }

    /**
     * Send agent message
     */
    public function sendAgentMessage(int $conversationId, string $content, array $options = []): ChatMessage
    {
        $userId = $this->authContext->userId();

        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to send agent messages');
        }

        $message = $this->sendMessage(
            $conversationId,
            $content,
            ChatMessage::SENDER_AGENT,
            $userId,
            $options
        );

        // Update agent activity
        ChatAgentStatus::getOrCreate($userId)->recordActivity();

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
    public function transferConversation(int $conversationId, int $newAgentId, ?string $note = null): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $previousAgent = $conversation->assignedAgent;

        // Unassign from current agent
        $conversation->unassign();

        // Assign to new agent
        $conversation->assign($newAgentId);

        // Add system message about transfer
        $newAgent = ChatAgentStatus::where('user_id', $newAgentId)->first()?->user;
        $agentName = $newAgent?->name ?? 'another agent';
        $transferMessage = "Conversation transferred to {$agentName}";
        if ($note) {
            $transferMessage .= ". Note: {$note}";
        }

        $conversation->addMessage($transferMessage, ChatMessage::SENDER_SYSTEM, null, ['is_internal' => true]);

        return $conversation->fresh(['assignedAgent']);
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
        $query = ChatMessage::where('conversation_id', $conversationId)
            ->whereNull('read_at');

        // Mark messages from the opposite party as read
        if ($readerType === 'agent') {
            $query->where('sender_type', ChatMessage::SENDER_VISITOR);
        } else {
            $query->where('sender_type', ChatMessage::SENDER_AGENT);
        }

        return $query->update(['read_at' => now()]);
    }

    // =========================================================================
    // AGENT STATUS USE CASES
    // =========================================================================

    /**
     * Get agent status
     */
    public function getAgentStatus(?int $userId = null): ChatAgentStatus
    {
        return ChatAgentStatus::getOrCreate($userId ?? $this->authContext->userId());
    }

    /**
     * Set agent online
     */
    public function setAgentOnline(?int $userId = null): ChatAgentStatus
    {
        $status = ChatAgentStatus::getOrCreate($userId ?? $this->authContext->userId());
        $status->setOnline();
        return $status->fresh();
    }

    /**
     * Set agent away
     */
    public function setAgentAway(?int $userId = null): ChatAgentStatus
    {
        $status = ChatAgentStatus::getOrCreate($userId ?? $this->authContext->userId());
        $status->setAway();
        return $status->fresh();
    }

    /**
     * Set agent busy
     */
    public function setAgentBusy(?int $userId = null): ChatAgentStatus
    {
        $status = ChatAgentStatus::getOrCreate($userId ?? $this->authContext->userId());
        $status->setBusy();
        return $status->fresh();
    }

    /**
     * Set agent offline
     */
    public function setAgentOffline(?int $userId = null): ChatAgentStatus
    {
        $status = ChatAgentStatus::getOrCreate($userId ?? $this->authContext->userId());
        $status->setOffline();
        return $status->fresh();
    }

    /**
     * Update agent settings
     */
    public function updateAgentSettings(array $data, ?int $userId = null): ChatAgentStatus
    {
        $status = ChatAgentStatus::getOrCreate($userId ?? $this->authContext->userId());

        $status->update([
            'max_conversations' => $data['max_conversations'] ?? $status->max_conversations,
            'departments' => $data['departments'] ?? $status->departments,
        ]);

        return $status->fresh();
    }

    /**
     * Get all online agents
     */
    public function getOnlineAgents(): Collection
    {
        return ChatAgentStatus::online()
            ->with(['user:id,name,email'])
            ->get();
    }

    /**
     * Get available agents for assignment
     */
    public function getAvailableAgents(?string $department = null): Collection
    {
        $query = ChatAgentStatus::available()->with(['user:id,name,email']);

        if ($department) {
            $query->inDepartment($department);
        }

        return $query->orderBy('active_conversations')->get();
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

        $query = ChatCannedResponse::forUser($userId);

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        return $query->orderBy('usage_count', 'desc')->get();
    }

    /**
     * Get canned response by shortcut
     */
    public function getCannedResponseByShortcut(string $shortcut): ?ChatCannedResponse
    {
        $userId = $this->authContext->userId();

        if (!$userId) {
            return null;
        }

        return ChatCannedResponse::forUser($userId)
            ->byShortcut($shortcut)
            ->first();
    }

    /**
     * Create canned response
     */
    public function createCannedResponse(array $data): ChatCannedResponse
    {
        $userId = $this->authContext->userId();

        if (!$userId) {
            throw new \RuntimeException('User must be authenticated to create canned responses');
        }

        return ChatCannedResponse::create([
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
    public function updateCannedResponse(int $responseId, array $data): ChatCannedResponse
    {
        $response = ChatCannedResponse::findOrFail($responseId);

        $response->update([
            'shortcut' => $data['shortcut'] ?? $response->shortcut,
            'title' => $data['title'] ?? $response->title,
            'content' => $data['content'] ?? $response->content,
            'category' => $data['category'] ?? $response->category,
            'is_global' => $data['is_global'] ?? $response->is_global,
        ]);

        return $response->fresh();
    }

    /**
     * Delete canned response
     */
    public function deleteCannedResponse(int $responseId): bool
    {
        return ChatCannedResponse::findOrFail($responseId)->delete();
    }

    /**
     * Use canned response in conversation
     */
    public function useCannedResponse(int $conversationId, int $responseId, array $variables = []): ChatMessage
    {
        $response = ChatCannedResponse::findOrFail($responseId);
        $response->incrementUsage();

        $content = $response->renderContent($variables);

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

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Find an available agent based on routing rules
     */
    private function findAvailableAgent(?array $routingRules, ?string $department): ?ChatAgentStatus
    {
        $query = ChatAgentStatus::available();

        if ($department) {
            $query->inDepartment($department);
        }

        // Round-robin: get agent with fewest active conversations
        return $query->orderBy('active_conversations')
            ->orderBy('last_activity_at', 'desc')
            ->first();
    }
}
