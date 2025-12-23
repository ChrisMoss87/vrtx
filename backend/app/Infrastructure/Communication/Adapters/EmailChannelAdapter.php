<?php

declare(strict_types=1);

namespace App\Infrastructure\Communication\Adapters;

use App\Domain\Communication\Contracts\SendMessageDTO;
use App\Domain\Communication\Entities\UnifiedConversation;
use App\Domain\Communication\Entities\UnifiedMessage;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\ConversationStatus;
use App\Domain\Communication\ValueObjects\MessageDirection;
use App\Domain\Communication\ValueObjects\MessageParticipant;
use App\Domain\Communication\ValueObjects\RecordContext;
use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Models\InboxConversation;
use App\Models\InboxMessage;

class EmailChannelAdapter extends AbstractChannelAdapter
{
    public function __construct(
        private readonly InboxConversationRepositoryInterface $inboxRepository,
    ) {}

    public function getChannelType(): ChannelType
    {
        return ChannelType::EMAIL;
    }

    public function isAvailable(): bool
    {
        return true; // Email is always available
    }

    public function getConversations(array $filters = [], int $perPage = 20, int $page = 1): PaginatedResult
    {
        // Add email channel filter
        $filters['channel'] = 'email';

        return $this->inboxRepository->list($filters, $perPage, $page);
    }

    public function getConversation(string $sourceId): ?UnifiedConversation
    {
        $conversation = InboxConversation::where('id', $sourceId)->first();

        if (!$conversation) {
            return null;
        }

        return $this->toUnifiedConversation($conversation);
    }

    public function getConversationsForRecord(RecordContext $context): array
    {
        $conversations = InboxConversation::where('contact_module', $context->moduleApiName)
            ->where('contact_id', $context->recordId)
            ->get();

        return $conversations->map(fn($c) => $this->toUnifiedConversation($c))->all();
    }

    public function getMessages(string $sourceConversationId, int $limit = 50): array
    {
        $messages = InboxMessage::where('conversation_id', $sourceConversationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $messages->map(fn($m) => $this->toUnifiedMessage($m))->all();
    }

    public function sendMessage(SendMessageDTO $message): UnifiedMessage
    {
        // Create inbox message
        $inboxMessage = InboxMessage::create([
            'conversation_id' => $message->conversationId,
            'direction' => 'outbound',
            'sender_type' => 'user',
            'sender_id' => $message->sender->userId,
            'sender_name' => $message->sender->name,
            'sender_email' => $message->sender->email,
            'content' => $message->content,
            'html_content' => $message->htmlContent,
            'attachments' => $message->attachments,
        ]);

        // Update conversation
        InboxConversation::where('id', $message->conversationId)->update([
            'last_message_at' => now(),
            'message_count' => \DB::raw('message_count + 1'),
        ]);

        return $this->toUnifiedMessage($inboxMessage);
    }

    public function toUnifiedConversation(mixed $sourceConversation): UnifiedConversation
    {
        $conversation = $sourceConversation;

        $contact = MessageParticipant::fromEmail(
            email: $conversation->contact_email ?? '',
            name: $conversation->contact_name,
        );

        if ($conversation->contact_id && $conversation->contact_module) {
            $contact = MessageParticipant::fromContact(
                name: $conversation->contact_name ?? '',
                email: $conversation->contact_email,
                phone: $conversation->contact_phone,
                recordContext: new RecordContext(
                    $conversation->contact_module,
                    $conversation->contact_id
                ),
            );
        }

        $linkedRecord = null;
        if ($conversation->linked_module && $conversation->linked_record_id) {
            $linkedRecord = new RecordContext(
                $conversation->linked_module,
                $conversation->linked_record_id
            );
        }

        return UnifiedConversation::reconstitute(
            id: $conversation->id,
            channel: ChannelType::EMAIL,
            status: $this->mapStatus($conversation->status ?? 'open'),
            subject: $conversation->subject,
            contact: $contact,
            assignedTo: $conversation->assigned_to,
            linkedRecord: $linkedRecord,
            sourceConversationId: (string) $conversation->id,
            externalThreadId: $conversation->external_thread_id,
            tags: $conversation->tags ?? [],
            messageCount: $conversation->message_count ?? 0,
            lastMessageAt: $conversation->last_message_at
                ? new \DateTimeImmutable($conversation->last_message_at)
                : null,
            firstResponseAt: $conversation->first_response_at
                ? new \DateTimeImmutable($conversation->first_response_at)
                : null,
            responseTimeSeconds: $conversation->response_time_seconds,
            metadata: $conversation->metadata ?? [],
            createdAt: new \DateTimeImmutable($conversation->created_at),
            updatedAt: $conversation->updated_at
                ? new \DateTimeImmutable($conversation->updated_at)
                : null,
        );
    }

    public function toUnifiedMessage(mixed $sourceMessage): UnifiedMessage
    {
        $message = $sourceMessage;

        $sender = $message->sender_type === 'user'
            ? MessageParticipant::fromUser(
                userId: $message->sender_id,
                name: $message->sender_name ?? 'Agent',
                email: $message->sender_email,
            )
            : MessageParticipant::fromEmail(
                email: $message->sender_email ?? '',
                name: $message->sender_name,
            );

        return UnifiedMessage::reconstitute(
            id: $message->id,
            conversationId: $message->conversation_id,
            channel: ChannelType::EMAIL,
            direction: $this->mapDirection($message->direction ?? 'inbound'),
            content: $message->content,
            htmlContent: $message->html_content,
            sender: $sender,
            recipients: [], // Would need to parse from message data
            attachments: $message->attachments ?? [],
            sourceMessageId: (string) $message->id,
            externalMessageId: $message->external_message_id,
            status: $message->status ?? UnifiedMessage::STATUS_SENT,
            sentAt: $message->sent_at ? new \DateTimeImmutable($message->sent_at) : null,
            deliveredAt: $message->delivered_at ? new \DateTimeImmutable($message->delivered_at) : null,
            readAt: $message->read_at ? new \DateTimeImmutable($message->read_at) : null,
            metadata: $message->metadata ?? [],
            createdAt: new \DateTimeImmutable($message->created_at),
        );
    }

    public function sync(?int $userId = null): int
    {
        // Email sync would be handled by the existing EmailService
        // This just returns 0 as emails are synced separately
        return 0;
    }
}
