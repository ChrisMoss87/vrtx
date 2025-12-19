<?php

declare(strict_types=1);

namespace App\Application\Services\Chat;

use App\Domain\Chat\Repositories\ChatConversationRepositoryInterface;
use App\Models\ChatAgentStatus;
use App\Models\ChatCannedResponse;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatVisitor;
use App\Models\ChatWidget;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatApplicationService
{
    public function __construct(
        private ChatConversationRepositoryInterface $repository,
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
    public function listConversations(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ChatConversation::query()
            ->with(['visitor', 'assignedAgent', 'widget']);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by multiple statuses
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        // Open only
        if (!empty($filters['open_only'])) {
            $query->open();
        }

        // Filter by widget
        if (!empty($filters['widget_id'])) {
            $query->where('widget_id', $filters['widget_id']);
        }

        // Filter by assigned agent
        if (!empty($filters['assigned_to'])) {
            $query->assignedTo($filters['assigned_to']);
        }

        // Unassigned only
        if (!empty($filters['unassigned_only'])) {
            $query->unassigned();
        }

        // Filter by department
        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        // Filter by priority
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // Filter by visitor
        if (!empty($filters['visitor_id'])) {
            $query->where('visitor_id', $filters['visitor_id']);
        }

        // Filter by date range
        if (!empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        if (!empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'ilike', "%{$search}%")
                    ->orWhereHas('visitor', fn($vq) => $vq->where('email', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%"));
            });
        }

        // Sort
        $sortField = $filters['sort_by'] ?? 'last_message_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a single conversation with messages
     */
    public function getConversation(int $conversationId): ?ChatConversation
    {
        return ChatConversation::with([
            'visitor' => fn($q) => $q->with('contact'),
            'assignedAgent',
            'widget',
            'messages' => fn($q) => $q->orderBy('created_at'),
        ])->find($conversationId);
    }

    /**
     * Get conversation for a visitor's active session
     */
    public function getActiveConversationForVisitor(int $visitorId): ?ChatConversation
    {
        return ChatConversation::where('visitor_id', $visitorId)
            ->whereIn('status', [ChatConversation::STATUS_OPEN, ChatConversation::STATUS_PENDING])
            ->latest()
            ->first();
    }

    /**
     * Get unassigned conversations (queue)
     */
    public function getUnassignedConversations(int $limit = 50): Collection
    {
        return ChatConversation::unassigned()
            ->open()
            ->with(['visitor', 'widget'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get conversations for current agent
     */
    public function getMyConversations(): Collection
    {
        return ChatConversation::assignedTo(Auth::id())
            ->whereIn('status', [ChatConversation::STATUS_OPEN, ChatConversation::STATUS_PENDING])
            ->with(['visitor', 'widget'])
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * Get conversation statistics
     */
    public function getConversationStats(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $conversations = ChatConversation::whereBetween('created_at', [$start, $end])->get();
        $closedConversations = $conversations->where('status', ChatConversation::STATUS_CLOSED);

        // Calculate average response time
        $avgResponseTimeMinutes = $closedConversations
            ->map(fn($c) => $c->getFirstResponseTimeMinutes())
            ->filter()
            ->avg();

        // Calculate average resolution time
        $avgResolutionTimeMinutes = $closedConversations
            ->map(fn($c) => $c->getResolutionTimeMinutes())
            ->filter()
            ->avg();

        // Rating stats
        $ratedConversations = $closedConversations->whereNotNull('rating');

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_conversations' => $conversations->count(),
            'by_status' => [
                ChatConversation::STATUS_OPEN => $conversations->where('status', ChatConversation::STATUS_OPEN)->count(),
                ChatConversation::STATUS_PENDING => $conversations->where('status', ChatConversation::STATUS_PENDING)->count(),
                ChatConversation::STATUS_CLOSED => $closedConversations->count(),
            ],
            'avg_first_response_minutes' => round($avgResponseTimeMinutes ?? 0, 1),
            'avg_resolution_minutes' => round($avgResolutionTimeMinutes ?? 0, 1),
            'total_messages' => $conversations->sum('message_count'),
            'avg_messages_per_conversation' => round($conversations->avg('message_count') ?? 0, 1),
            'ratings' => [
                'count' => $ratedConversations->count(),
                'average' => round($ratedConversations->avg('rating') ?? 0, 1),
                'distribution' => $ratedConversations->groupBy(fn($c) => (int)$c->rating)->map->count(),
            ],
        ];
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
        $message = $this->sendMessage(
            $conversationId,
            $content,
            ChatMessage::SENDER_AGENT,
            Auth::id(),
            $options
        );

        // Update agent activity
        ChatAgentStatus::getOrCreate(Auth::id())->recordActivity();

        return $message;
    }

    /**
     * Assign conversation to agent
     */
    public function assignConversation(int $conversationId, int $userId): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $conversation->assign($userId);
        return $conversation->fresh(['assignedAgent']);
    }

    /**
     * Unassign conversation
     */
    public function unassignConversation(int $conversationId): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $conversation->unassign();
        return $conversation->fresh();
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
    public function closeConversation(int $conversationId): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $conversation->close();
        return $conversation;
    }

    /**
     * Reopen a conversation
     */
    public function reopenConversation(int $conversationId): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $conversation->reopen();
        return $conversation;
    }

    /**
     * Set conversation priority
     */
    public function setConversationPriority(int $conversationId, string $priority): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $conversation->update(['priority' => $priority]);
        return $conversation;
    }

    /**
     * Add tags to conversation
     */
    public function addConversationTags(int $conversationId, array $tags): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $existingTags = $conversation->tags ?? [];
        $conversation->update(['tags' => array_unique(array_merge($existingTags, $tags))]);
        return $conversation->fresh();
    }

    /**
     * Rate conversation (from visitor)
     */
    public function rateConversation(int $conversationId, float $rating, ?string $comment = null): ChatConversation
    {
        $conversation = ChatConversation::findOrFail($conversationId);
        $conversation->update([
            'rating' => max(1, min(5, $rating)),
            'rating_comment' => $comment,
        ]);
        return $conversation;
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
        return ChatAgentStatus::getOrCreate($userId ?? Auth::id());
    }

    /**
     * Set agent online
     */
    public function setAgentOnline(?int $userId = null): ChatAgentStatus
    {
        $status = ChatAgentStatus::getOrCreate($userId ?? Auth::id());
        $status->setOnline();
        return $status->fresh();
    }

    /**
     * Set agent away
     */
    public function setAgentAway(?int $userId = null): ChatAgentStatus
    {
        $status = ChatAgentStatus::getOrCreate($userId ?? Auth::id());
        $status->setAway();
        return $status->fresh();
    }

    /**
     * Set agent busy
     */
    public function setAgentBusy(?int $userId = null): ChatAgentStatus
    {
        $status = ChatAgentStatus::getOrCreate($userId ?? Auth::id());
        $status->setBusy();
        return $status->fresh();
    }

    /**
     * Set agent offline
     */
    public function setAgentOffline(?int $userId = null): ChatAgentStatus
    {
        $status = ChatAgentStatus::getOrCreate($userId ?? Auth::id());
        $status->setOffline();
        return $status->fresh();
    }

    /**
     * Update agent settings
     */
    public function updateAgentSettings(array $data, ?int $userId = null): ChatAgentStatus
    {
        $status = ChatAgentStatus::getOrCreate($userId ?? Auth::id());

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
        $query = ChatCannedResponse::forUser(Auth::id());

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
        return ChatCannedResponse::forUser(Auth::id())
            ->byShortcut($shortcut)
            ->first();
    }

    /**
     * Create canned response
     */
    public function createCannedResponse(array $data): ChatCannedResponse
    {
        return ChatCannedResponse::create([
            'shortcut' => $data['shortcut'],
            'title' => $data['title'],
            'content' => $data['content'],
            'category' => $data['category'] ?? null,
            'is_global' => $data['is_global'] ?? false,
            'created_by' => Auth::id(),
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
        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $conversations = ChatConversation::assignedTo($userId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $closedConversations = $conversations->where('status', ChatConversation::STATUS_CLOSED);

        return [
            'user_id' => $userId,
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'total_conversations' => $conversations->count(),
            'closed_conversations' => $closedConversations->count(),
            'avg_first_response_minutes' => round(
                $closedConversations->map(fn($c) => $c->getFirstResponseTimeMinutes())->filter()->avg() ?? 0,
                1
            ),
            'avg_resolution_minutes' => round(
                $closedConversations->map(fn($c) => $c->getResolutionTimeMinutes())->filter()->avg() ?? 0,
                1
            ),
            'avg_rating' => round($closedConversations->whereNotNull('rating')->avg('rating') ?? 0, 1),
            'total_messages_sent' => ChatMessage::whereHas('conversation', fn($q) => $q->assignedTo($userId))
                ->where('sender_type', ChatMessage::SENDER_AGENT)
                ->where('sender_id', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
        ];
    }

    /**
     * Get hourly chat volume
     */
    public function getHourlyChatVolume(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days);

        $volume = ChatConversation::where('created_at', '>=', $startDate)
            ->selectRaw("EXTRACT(HOUR FROM created_at) as hour, COUNT(*) as count")
            ->groupByRaw('EXTRACT(HOUR FROM created_at)')
            ->orderBy('hour')
            ->get();

        $result = array_fill(0, 24, 0);
        foreach ($volume as $row) {
            $result[(int)$row->hour] = $row->count;
        }

        return $result;
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
