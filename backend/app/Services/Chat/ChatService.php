<?php

namespace App\Services\Chat;

use App\Models\ChatWidget;
use App\Models\ChatVisitor;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatAgentStatus;
use Illuminate\Support\Collection;

class ChatService
{
    public function createWidget(array $data): ChatWidget
    {
        $widget = new ChatWidget();
        $widget->name = $data['name'];
        $widget->is_active = $data['is_active'] ?? true;
        $widget->settings = array_merge($widget->getDefaultSettings(), $data['settings'] ?? []);
        $widget->styling = array_merge($widget->getDefaultStyling(), $data['styling'] ?? []);
        $widget->routing_rules = $data['routing_rules'] ?? null;
        $widget->business_hours = $data['business_hours'] ?? null;
        $widget->allowed_domains = $data['allowed_domains'] ?? null;
        $widget->save();

        return $widget;
    }

    public function updateWidget(ChatWidget $widget, array $data): ChatWidget
    {
        if (isset($data['name'])) {
            $widget->name = $data['name'];
        }
        if (isset($data['is_active'])) {
            $widget->is_active = $data['is_active'];
        }
        if (isset($data['settings'])) {
            $widget->settings = array_merge($widget->settings ?? [], $data['settings']);
        }
        if (isset($data['styling'])) {
            $widget->styling = array_merge($widget->styling ?? [], $data['styling']);
        }
        if (array_key_exists('routing_rules', $data)) {
            $widget->routing_rules = $data['routing_rules'];
        }
        if (array_key_exists('business_hours', $data)) {
            $widget->business_hours = $data['business_hours'];
        }
        if (array_key_exists('allowed_domains', $data)) {
            $widget->allowed_domains = $data['allowed_domains'];
        }

        $widget->save();
        return $widget;
    }

    public function getOrCreateVisitor(ChatWidget $widget, string $fingerprint, array $data = []): ChatVisitor
    {
        $visitor = ChatVisitor::where('widget_id', $widget->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if (!$visitor) {
            $visitor = ChatVisitor::create([
                'widget_id' => $widget->id,
                'fingerprint' => $fingerprint,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'referrer' => $data['referrer'] ?? null,
                'current_page' => $data['current_page'] ?? null,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        } else {
            $visitor->update([
                'last_seen_at' => now(),
                'current_page' => $data['current_page'] ?? $visitor->current_page,
            ]);
        }

        return $visitor;
    }

    public function startConversation(ChatVisitor $visitor, array $data = []): ChatConversation
    {
        // Check for existing open conversation
        $existingConversation = ChatConversation::where('visitor_id', $visitor->id)
            ->where('status', ChatConversation::STATUS_OPEN)
            ->first();

        if ($existingConversation) {
            return $existingConversation;
        }

        $conversation = ChatConversation::create([
            'widget_id' => $visitor->widget_id,
            'visitor_id' => $visitor->id,
            'contact_id' => $visitor->contact_id,
            'status' => ChatConversation::STATUS_OPEN,
            'priority' => $data['priority'] ?? ChatConversation::PRIORITY_NORMAL,
            'department' => $data['department'] ?? null,
            'subject' => $data['subject'] ?? null,
        ]);

        // Auto-assign if routing rules exist
        $this->autoAssignConversation($conversation);

        return $conversation;
    }

    public function autoAssignConversation(ChatConversation $conversation): void
    {
        $widget = $conversation->widget;
        $routingRules = $widget->routing_rules ?? [];
        $department = $conversation->department;

        // Find available agent
        $query = ChatAgentStatus::available();

        if ($department) {
            $query->inDepartment($department);
        }

        // Round-robin: get agent with least active conversations
        $agent = $query->orderBy('active_conversations')->first();

        if ($agent) {
            $conversation->assign($agent->user_id);
        }
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
        $newAgent = \App\Models\User::find($toUserId);
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
