<?php

namespace App\Services\Chat;

use App\Domain\Chat\Entities\ChatWidget;
use App\Domain\Chat\Entities\ChatVisitor;
use App\Domain\Chat\Entities\ChatConversation;
use App\Domain\Chat\Entities\ChatMessage;
use App\Domain\Chat\Repositories\ChatConversationRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function __construct(
        protected ChatConversationRepositoryInterface $conversationRepository,
    ) {}

    public function createWidget(array $data): ChatWidget
    {
        $widget = ChatWidget::create(
            name: $data['name'],
            isActive: $data['is_active'] ?? true,
            settings: array_merge(ChatWidget::getDefaultSettings(), $data['settings'] ?? []),
            styling: array_merge(ChatWidget::getDefaultStyling(), $data['styling'] ?? []),
            routingRules: $data['routing_rules'] ?? null,
            businessHours: $data['business_hours'] ?? null,
            allowedDomains: $data['allowed_domains'] ?? null
        );

        return $widget;
    }

    public function updateWidget(ChatWidget $widget, array $data): ChatWidget
    {
        if (isset($data['name'])) {
            $widget->setName($data['name']);
        }
        if (isset($data['is_active'])) {
            $widget->setIsActive($data['is_active']);
        }
        if (isset($data['settings'])) {
            $widget->updateSettings($data['settings']);
        }
        if (isset($data['styling'])) {
            $widget->updateStyling($data['styling']);
        }
        if (array_key_exists('routing_rules', $data)) {
            $widget->setRoutingRules($data['routing_rules']);
        }
        if (array_key_exists('business_hours', $data)) {
            $widget->setBusinessHours($data['business_hours']);
        }
        if (array_key_exists('allowed_domains', $data)) {
            $widget->setAllowedDomains($data['allowed_domains']);
        }

        return $widget;
    }

    public function getOrCreateVisitor(ChatWidget $widget, string $fingerprint, array $data = []): ChatVisitor
    {
        // This would use visitor repository
        // For now, simplified implementation
        $visitor = ChatVisitor::create(
            widgetId: $widget->getId(),
            fingerprint: $fingerprint,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            country: $data['country'] ?? null,
            city: $data['city'] ?? null,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            referrer: $data['referrer'] ?? null,
            currentPage: $data['current_page'] ?? null,
            firstSeenAt: now(),
            lastSeenAt: now()
        );

        return $visitor;
    }

    public function startConversation(ChatVisitor $visitor, array $data = []): ChatConversation
    {
        // Check for existing open conversation
        $existingConversation = $this->conversationRepository->findOpenByVisitor($visitor->getId());

        if ($existingConversation) {
            return $existingConversation;
        }

        $conversation = ChatConversation::create(
            widgetId: $visitor->getWidgetId(),
            visitorId: $visitor->getId(),
            contactId: $visitor->getContactId(),
            status: 'open',
            priority: $data['priority'] ?? 'normal',
            department: $data['department'] ?? null,
            subject: $data['subject'] ?? null
        );

        $conversation = $this->conversationRepository->save($conversation);

        // Auto-assign if routing rules exist
        $this->autoAssignConversation($conversation);

        return $conversation;
    }

    public function autoAssignConversation(ChatConversation $conversation): void
    {
        // Simplified - would need agent status repository
        // Auto-assignment logic would go here
    }

    public function sendMessage(
        ChatConversation $conversation,
        string $content,
        string $senderType,
        ?int $senderId = null,
        array $options = []
    ): ChatMessage {
        return $conversation->addMessage($content, $senderType, $senderId, $options);
    }

    public function getConversationsForAgent(int $userId, ?string $status = null): Collection
    {
        $query = ChatConversation::with(['visitor', 'messages' => function ($q) {
            $q->latest()->limit(1);
        }])
            ->where('assigned_to', $userId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('last_message_at')->get();
    }

    public function getUnassignedConversations(?string $department = null): Collection
    {
        $query = ChatConversation::with(['visitor', 'widget'])
            ->whereNull('assigned_to')
            ->where('status', ChatConversation::STATUS_OPEN);

        if ($department) {
            $query->where('department', $department);
        }

        return $query->orderBy('created_at')->get();
    }

    public function closeConversation(ChatConversation $conversation, ?string $systemMessage = null): void
    {
        if ($systemMessage) {
            $conversation->addMessage($systemMessage, ChatMessage::SENDER_SYSTEM);
        }

        $conversation->close();
    }

    public function transferConversation(ChatConversation $conversation, int $toUserId): void
    {
        $previousAgent = $conversation->assigned_to;

        $conversation->update(['assigned_to' => $toUserId]);

        // Update agent statuses
        if ($previousAgent) {
            ChatAgentStatus::where('user_id', $previousAgent)
                ->where('active_conversations', '>', 0)
                ->decrement('active_conversations');
        }

        ChatAgentStatus::where('user_id', $toUserId)->increment('active_conversations');

        // Add system message
        $newAgent = DB::table('users')->where('id', $toUserId)->first();
        $conversation->addMessage(
            "Conversation transferred to {$newAgent->name}",
            ChatMessage::SENDER_SYSTEM
        );
    }

    public function getWidgetConfig(ChatWidget $widget): array
    {
        return [
            'widget_key' => $widget->widget_key,
            'is_online' => $widget->isOnline(),
            'settings' => $widget->settings ?? $widget->getDefaultSettings(),
            'styling' => $widget->styling ?? $widget->getDefaultStyling(),
        ];
    }
}
