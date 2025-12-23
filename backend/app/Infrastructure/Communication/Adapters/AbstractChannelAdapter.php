<?php

declare(strict_types=1);

namespace App\Infrastructure\Communication\Adapters;

use App\Domain\Communication\Contracts\CommunicationChannelInterface;
use App\Domain\Communication\Entities\UnifiedConversation;
use App\Domain\Communication\Entities\UnifiedMessage;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\ConversationStatus;
use App\Domain\Communication\ValueObjects\MessageDirection;
use App\Domain\Communication\ValueObjects\MessageParticipant;
use App\Domain\Communication\ValueObjects\RecordContext;

abstract class AbstractChannelAdapter implements CommunicationChannelInterface
{
    abstract public function getChannelType(): ChannelType;

    abstract public function isAvailable(): bool;

    /**
     * Helper to create a unified conversation from channel-specific data.
     */
    protected function createUnifiedConversation(
        string $sourceId,
        ?string $subject,
        MessageParticipant $contact,
        ConversationStatus $status,
        ?int $assignedTo = null,
        ?RecordContext $linkedRecord = null,
        array $tags = [],
        int $messageCount = 0,
        ?\DateTimeImmutable $lastMessageAt = null,
        ?string $externalThreadId = null,
        array $metadata = [],
    ): UnifiedConversation {
        $conversation = UnifiedConversation::create(
            channel: $this->getChannelType(),
            contact: $contact,
            subject: $subject,
            sourceConversationId: $sourceId,
        );

        if ($status !== ConversationStatus::OPEN) {
            $conversation->updateStatus($status);
        }

        if ($assignedTo) {
            $conversation->assign($assignedTo);
        }

        if ($linkedRecord) {
            $conversation->linkToRecord($linkedRecord);
        }

        foreach ($tags as $tag) {
            $conversation->addTag($tag);
        }

        return $conversation;
    }

    /**
     * Helper to create a unified message from channel-specific data.
     */
    protected function createUnifiedMessage(
        int $conversationId,
        MessageDirection $direction,
        MessageParticipant $sender,
        array $recipients,
        ?string $content,
        ?string $htmlContent = null,
        ?string $sourceMessageId = null,
        ?string $externalMessageId = null,
        string $status = UnifiedMessage::STATUS_SENT,
        ?\DateTimeImmutable $sentAt = null,
        array $attachments = [],
        array $metadata = [],
    ): UnifiedMessage {
        $message = UnifiedMessage::create(
            conversationId: $conversationId,
            channel: $this->getChannelType(),
            direction: $direction,
            sender: $sender,
            recipients: $recipients,
            content: $content,
            htmlContent: $htmlContent,
            sourceMessageId: $sourceMessageId,
        );

        return $message;
    }

    /**
     * Helper to convert status string to ConversationStatus.
     */
    protected function mapStatus(string $status): ConversationStatus
    {
        return match (strtolower($status)) {
            'open', 'active', 'new' => ConversationStatus::OPEN,
            'pending', 'waiting', 'hold' => ConversationStatus::PENDING,
            'resolved', 'completed' => ConversationStatus::RESOLVED,
            'closed', 'archived' => ConversationStatus::CLOSED,
            default => ConversationStatus::OPEN,
        };
    }

    /**
     * Helper to map direction string.
     */
    protected function mapDirection(string $direction): MessageDirection
    {
        return match (strtolower($direction)) {
            'inbound', 'incoming', 'received' => MessageDirection::INBOUND,
            'outbound', 'outgoing', 'sent' => MessageDirection::OUTBOUND,
            default => MessageDirection::INBOUND,
        };
    }
}
