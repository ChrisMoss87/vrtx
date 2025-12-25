<?php

declare(strict_types=1);

namespace App\Application\Services\WhatsApp;

use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\WhatsApp\Repositories\WhatsappConversationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WhatsAppApplicationService
{
    public function __construct(
        private WhatsappConversationRepositoryInterface $repository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - CONNECTIONS
    // =========================================================================

    /**
     * List WhatsApp connections.
     */
    public function listConnections(array $filters = []): Collection
    {
        $query = DB::table('whatsapp_connections');

        if (!empty($filters['active'])) {
            $query->active();
        }

        if (!empty($filters['verified'])) {
            $query->verified();
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a single connection by ID.
     */
    public function getConnection(int $id): ?WhatsappConnection
    {
        return DB::table('whatsapp_connections')->where('id', $id)->first();
    }

    /**
     * Get active connections.
     */
    public function getActiveConnections(): Collection
    {
        return WhatsappConnection::active()->verified()->orderBy('name')->get();
    }

    // =========================================================================
    // COMMAND USE CASES - CONNECTIONS
    // =========================================================================

    /**
     * Create a WhatsApp connection.
     */
    public function createConnection(array $data): WhatsappConnection
    {
        return DB::table('whatsapp_connections')->insertGetId([
            'name' => $data['name'],
            'phone_number_id' => $data['phone_number_id'],
            'waba_id' => $data['waba_id'],
            'access_token' => $data['access_token'],
            'display_phone_number' => $data['display_phone_number'] ?? null,
            'verified_name' => $data['verified_name'] ?? null,
            'quality_rating' => $data['quality_rating'] ?? 'UNKNOWN',
            'messaging_limit' => $data['messaging_limit'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_verified' => $data['is_verified'] ?? false,
            'settings' => $data['settings'] ?? [],
        ]);
    }

    /**
     * Update a WhatsApp connection.
     */
    public function updateConnection(int $id, array $data): WhatsappConnection
    {
        $connection = DB::table('whatsapp_connections')->where('id', $id)->first();

        $updateData = [];

        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['access_token'])) $updateData['access_token'] = $data['access_token'];
        if (isset($data['display_phone_number'])) $updateData['display_phone_number'] = $data['display_phone_number'];
        if (isset($data['verified_name'])) $updateData['verified_name'] = $data['verified_name'];
        if (isset($data['quality_rating'])) $updateData['quality_rating'] = $data['quality_rating'];
        if (isset($data['messaging_limit'])) $updateData['messaging_limit'] = $data['messaging_limit'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];
        if (isset($data['is_verified'])) $updateData['is_verified'] = $data['is_verified'];
        if (isset($data['settings'])) $updateData['settings'] = array_merge($connection->settings ?? [], $data['settings']);
        if (isset($data['last_synced_at'])) $updateData['last_synced_at'] = $data['last_synced_at'];

        $connection->update($updateData);

        return $connection->fresh();
    }

    /**
     * Delete a WhatsApp connection.
     */
    public function deleteConnection(int $id): bool
    {
        $connection = DB::table('whatsapp_connections')->where('id', $id)->first();

        // Check if connection has active conversations
        if ($connection->conversations()->unresolved()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete connection with unresolved conversations');
        }

        return $connection->delete();
    }

    // =========================================================================
    // QUERY USE CASES - CONVERSATIONS
    // =========================================================================

    /**
     * List conversations with filtering and pagination.
     */
    public function listConversations(array $filters = [], int $perPage = 25): PaginatedResult
    {
        $page = $filters['page'] ?? 1;
        return $this->repository->list($filters, $perPage, $page);
    }

    /**
     * Get a single conversation by ID.
     */
    public function getConversation(int $id): ?array
    {
        return $this->repository->findById($id, [
            'connection:id,name,display_phone_number',
            'assignedUser:id,name,email',
            'messages' => function ($q) {
                $q->orderBy('created_at', 'asc')->with('sender:id,name');
            }
        ]);
    }

    /**
     * Get conversations for a user.
     */
    public function getMyConversations(?int $userId = null, array $filters = []): array
    {
        $userId = $userId ?? $this->authContext->userId();

        return $this->repository->findByAssignedUser($userId, $filters);
    }

    /**
     * Get unread conversations count for a user.
     */
    public function getUnreadCount(?int $userId = null): int
    {
        $userId = $userId ?? $this->authContext->userId();

        return $this->repository->countUnreadByUser($userId);
    }

    // =========================================================================
    // COMMAND USE CASES - CONVERSATIONS
    // =========================================================================

    /**
     * Create or get conversation for a contact.
     */
    public function getOrCreateConversation(int $connectionId, string $contactWaId, string $contactPhone, ?string $contactName = null): array
    {
        return $this->repository->getOrCreate($connectionId, $contactWaId, $contactPhone, $contactName);
    }

    /**
     * Update conversation details.
     */
    public function updateConversation(int $id, array $data): ?array
    {
        return $this->repository->update($id, $data);
    }

    /**
     * Assign conversation to a user.
     */
    public function assignConversation(int $id, int $userId): ?array
    {
        $this->repository->assign($id, $userId);
        return $this->repository->findById($id);
    }

    /**
     * Link conversation to a module record.
     */
    public function linkToRecord(int $id, string $moduleApiName, int $recordId): ?array
    {
        $this->repository->linkToRecord($id, $moduleApiName, $recordId);
        return $this->repository->findById($id);
    }

    /**
     * Mark conversation as read.
     */
    public function markAsRead(int $id): ?array
    {
        $this->repository->markAsRead($id);
        return $this->repository->findById($id);
    }

    /**
     * Close a conversation.
     */
    public function closeConversation(int $id): ?array
    {
        $this->repository->close($id);
        return $this->repository->findById($id);
    }

    /**
     * Reopen a conversation.
     */
    public function reopenConversation(int $id): ?array
    {
        $this->repository->reopen($id);
        return $this->repository->findById($id);
    }

    // =========================================================================
    // QUERY USE CASES - MESSAGES
    // =========================================================================

    /**
     * List messages for a conversation.
     */
    public function listMessages(int $conversationId, int $limit = 100): Collection
    {
        return DB::table('whatsapp_messages')->where('conversation_id', $conversationId)
            ->with(['sender:id,name', 'template:id,name'])
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get a single message by ID.
     */
    public function getMessage(int $id): ?WhatsappMessage
    {
        return WhatsappMessage::with(['conversation', 'sender:id,name', 'template'])->find($id);
    }

    /**
     * Get message by WhatsApp message ID.
     */
    public function getMessageByWaId(string $waMessageId): ?WhatsappMessage
    {
        return DB::table('whatsapp_messages')->where('wa_message_id', $waMessageId)
            ->with('conversation')
            ->first();
    }

    // =========================================================================
    // COMMAND USE CASES - MESSAGES
    // =========================================================================

    /**
     * Send a text message.
     */
    public function sendMessage(int $conversationId, string $content, ?int $userId = null): WhatsappMessage
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        return DB::transaction(function () use ($conversation, $content, $userId) {
            $message = DB::table('whatsapp_messages')->insertGetId([
                'conversation_id' => $conversation['id'],
                'connection_id' => $conversation['connection_id'],
                'direction' => 'outbound',
                'type' => 'text',
                'content' => $content,
                'status' => 'pending',
                'sent_by' => $userId ?? $this->authContext->userId(),
            ]);

            // Update conversation last message timestamp
            $this->repository->updateTimestamps($conversation['id'], [
                'last_message_at' => now(),
                'last_outgoing_at' => now(),
            ]);

            return $message;
        });
    }

    /**
     * Send a template message.
     */
    public function sendTemplateMessage(int $conversationId, int $templateId, array $params = [], ?int $userId = null): WhatsappMessage
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        $template = DB::table('whatsapp_templates')->where('id', $templateId)->first();

        if (!$template->isUsable()) {
            throw new \InvalidArgumentException('Template is not usable');
        }

        return DB::transaction(function () use ($conversation, $templateId, $params, $userId) {
            $message = DB::table('whatsapp_messages')->insertGetId([
                'conversation_id' => $conversation['id'],
                'connection_id' => $conversation['connection_id'],
                'direction' => 'outbound',
                'type' => 'template',
                'template_id' => $templateId,
                'template_params' => $params,
                'status' => 'pending',
                'sent_by' => $userId ?? $this->authContext->userId(),
            ]);

            // Update conversation last message timestamp
            $this->repository->updateTimestamps($conversation['id'], [
                'last_message_at' => now(),
                'last_outgoing_at' => now(),
            ]);

            return $message;
        });
    }

    /**
     * Send a media message.
     */
    public function sendMediaMessage(int $conversationId, string $type, array $media, ?string $caption = null, ?int $userId = null): WhatsappMessage
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        return DB::transaction(function () use ($conversation, $type, $media, $caption, $userId) {
            $message = DB::table('whatsapp_messages')->insertGetId([
                'conversation_id' => $conversation['id'],
                'connection_id' => $conversation['connection_id'],
                'direction' => 'outbound',
                'type' => $type,
                'content' => $caption,
                'media' => $media,
                'status' => 'pending',
                'sent_by' => $userId ?? $this->authContext->userId(),
            ]);

            // Update conversation last message timestamp
            $this->repository->updateTimestamps($conversation['id'], [
                'last_message_at' => now(),
                'last_outgoing_at' => now(),
            ]);

            return $message;
        });
    }

    /**
     * Receive/store an inbound message.
     */
    public function receiveMessage(int $conversationId, string $waMessageId, string $type, ?string $content = null, ?array $media = null): WhatsappMessage
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        return DB::transaction(function () use ($conversation, $waMessageId, $type, $content, $media) {
            $message = DB::table('whatsapp_messages')->insertGetId([
                'conversation_id' => $conversation['id'],
                'connection_id' => $conversation['connection_id'],
                'wa_message_id' => $waMessageId,
                'direction' => 'inbound',
                'type' => $type,
                'content' => $content,
                'media' => $media,
                'status' => 'received',
            ]);

            // Update conversation
            $this->repository->updateTimestamps($conversation['id'], [
                'last_message_at' => now(),
                'last_incoming_at' => now(),
            ]);
            $this->repository->incrementUnread($conversation['id']);

            return $message;
        });
    }

    /**
     * Mark message as sent (received by WhatsApp).
     */
    public function markMessageSent(int $messageId, string $waMessageId): WhatsappMessage
    {
        $message = DB::table('whatsapp_messages')->where('id', $messageId)->first();
        $message->markAsSent($waMessageId);
        return $message->fresh();
    }

    /**
     * Mark message as delivered.
     */
    public function markMessageDelivered(string $waMessageId): ?WhatsappMessage
    {
        $message = $this->getMessageByWaId($waMessageId);

        if ($message) {
            $message->markAsDelivered();
            return $message->fresh();
        }

        return null;
    }

    /**
     * Mark message as read.
     */
    public function markMessageRead(string $waMessageId): ?WhatsappMessage
    {
        $message = $this->getMessageByWaId($waMessageId);

        if ($message) {
            $message->markAsRead();
            return $message->fresh();
        }

        return null;
    }

    /**
     * Mark message as failed.
     */
    public function markMessageFailed(int $messageId, string $errorCode, string $errorMessage): WhatsappMessage
    {
        $message = DB::table('whatsapp_messages')->where('id', $messageId)->first();
        $message->markAsFailed($errorCode, $errorMessage);
        return $message->fresh();
    }

    // =========================================================================
    // QUERY USE CASES - TEMPLATES
    // =========================================================================

    /**
     * List templates with filtering.
     */
    public function listTemplates(array $filters = []): Collection
    {
        $query = DB::table('whatsapp_templates')
            ->with('connection:id,name');

        // Filter by connection
        if (!empty($filters['connection_id'])) {
            $query->where('connection_id', $filters['connection_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by category
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        // Filter active/approved only
        if (!empty($filters['active'])) {
            $query->active();
        }

        if (!empty($filters['approved'])) {
            $query->approved();
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a single template by ID.
     */
    public function getTemplate(int $id): ?WhatsappTemplate
    {
        return WhatsappTemplate::with(['connection:id,name', 'creator:id,name'])->find($id);
    }

    /**
     * Get approved templates for a connection.
     */
    public function getApprovedTemplates(int $connectionId): Collection
    {
        return DB::table('whatsapp_templates')->where('connection_id', $connectionId)
            ->approved()
            ->orderBy('name')
            ->get();
    }

    // =========================================================================
    // COMMAND USE CASES - TEMPLATES
    // =========================================================================

    /**
     * Create a WhatsApp template.
     */
    public function createTemplate(array $data): WhatsappTemplate
    {
        return DB::table('whatsapp_templates')->insertGetId([
            'connection_id' => $data['connection_id'],
            'template_id' => $data['template_id'] ?? null,
            'name' => $data['name'],
            'language' => $data['language'] ?? 'en',
            'category' => $data['category'],
            'status' => $data['status'] ?? 'PENDING',
            'components' => $data['components'] ?? [],
            'example' => $data['example'] ?? [],
            'created_by' => $this->authContext->userId(),
        ]);
    }

    /**
     * Update a template.
     */
    public function updateTemplate(int $id, array $data): WhatsappTemplate
    {
        $template = DB::table('whatsapp_templates')->where('id', $id)->first();

        $updateData = [];

        if (isset($data['template_id'])) $updateData['template_id'] = $data['template_id'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['rejection_reason'])) $updateData['rejection_reason'] = $data['rejection_reason'];
        if (isset($data['submitted_at'])) $updateData['submitted_at'] = $data['submitted_at'];
        if (isset($data['approved_at'])) $updateData['approved_at'] = $data['approved_at'];

        $template->update($updateData);

        return $template->fresh();
    }

    /**
     * Delete a template.
     */
    public function deleteTemplate(int $id): bool
    {
        $template = DB::table('whatsapp_templates')->where('id', $id)->first();
        return $template->delete();
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get conversation statistics.
     */
    public function getConversationStats(?int $connectionId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $stats = $this->repository->getStats($connectionId, $fromDate, $toDate);

        $avgResponseTime = DB::table('whatsapp_messages')
            ->where('direction', 'outbound')
            ->whereNotNull('sent_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as avg_seconds')
            ->value('avg_seconds');

        $stats['avg_response_time_seconds'] = $avgResponseTime ? round($avgResponseTime) : null;

        return $stats;
    }

    /**
     * Get message statistics.
     */
    public function getMessageStats(?int $connectionId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DB::table('whatsapp_messages');

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
        }

        $total = $query->count();
        $inbound = (clone $query)->inbound()->count();
        $outbound = (clone $query)->outbound()->count();
        $delivered = (clone $query)->delivered()->count();
        $read = (clone $query)->read()->count();
        $failed = (clone $query)->failed()->count();

        $byType = DB::table('whatsapp_messages')
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total_messages' => $total,
            'inbound' => $inbound,
            'outbound' => $outbound,
            'delivered' => $delivered,
            'read' => $read,
            'failed' => $failed,
            'delivery_rate' => $outbound > 0 ? round(($delivered / $outbound) * 100, 2) : 0,
            'read_rate' => $delivered > 0 ? round(($read / $delivered) * 100, 2) : 0,
            'by_type' => $byType,
        ];
    }

    /**
     * Get daily message count for dashboard.
     */
    public function getDailyMessageCount(?int $connectionId = null, int $days = 30): Collection
    {
        $query = DB::table('whatsapp_messages')
            ->selectRaw('DATE(created_at) as date, direction, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date', 'direction')
            ->orderBy('date');

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        return $query->get();
    }

    /**
     * Get template usage statistics.
     */
    public function getTemplateUsageStats(?int $connectionId = null): array
    {
        $query = DB::table('whatsapp_templates');

        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        $total = $query->count();
        $approved = (clone $query)->approved()->count();
        $pending = (clone $query)->where('status', 'PENDING')->count();
        $rejected = (clone $query)->where('status', 'REJECTED')->count();

        $usage = DB::table('whatsapp_messages')
            ->where('type', 'template')
            ->whereNotNull('template_id')
            ->selectRaw('template_id, COUNT(*) as usage_count')
            ->groupBy('template_id')
            ->with('template:id,name')
            ->get();

        return [
            'total_templates' => $total,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'most_used' => $usage->sortByDesc('usage_count')->take(10)->map(function ($item) {
                return [
                    'template_name' => $item->template?->name,
                    'usage_count' => $item->usage_count,
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Get agent performance statistics.
     */
    public function getAgentStats(?int $userId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $userId = $userId ?? $this->authContext->userId();

        $conversationsQuery = DB::table('whatsapp_conversations')->where('assigned_to', $userId);

        if ($fromDate) {
            $conversationsQuery->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $conversationsQuery->where('created_at', '<=', $toDate);
        }

        $totalConversations = $conversationsQuery->count();
        $resolvedConversations = (clone $conversationsQuery)->where('is_resolved', true)->count();

        $messagesQuery = DB::table('whatsapp_messages')->where('sent_by', $userId)->outbound();

        if ($fromDate) {
            $messagesQuery->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $messagesQuery->where('created_at', '<=', $toDate);
        }

        $messagesSent = $messagesQuery->count();

        $avgResponseTime = DB::table('whatsapp_messages')->where('sent_by', $userId)
            ->outbound()
            ->whereNotNull('sent_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as avg_seconds')
            ->value('avg_seconds');

        return [
            'total_conversations' => $totalConversations,
            'resolved_conversations' => $resolvedConversations,
            'messages_sent' => $messagesSent,
            'avg_response_time_seconds' => $avgResponseTime ? round($avgResponseTime) : null,
            'resolution_rate' => $totalConversations > 0 ? round(($resolvedConversations / $totalConversations) * 100, 2) : 0,
        ];
    }
}
