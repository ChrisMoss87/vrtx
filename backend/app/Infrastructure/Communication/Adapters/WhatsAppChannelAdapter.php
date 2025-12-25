<?php

declare(strict_types=1);

namespace App\Infrastructure\Communication\Adapters;

use App\Domain\Communication\Contracts\SendMessageDTO;
use App\Domain\Communication\Entities\UnifiedConversation;
use App\Domain\Communication\Entities\UnifiedMessage;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\MessageDirection;
use App\Domain\Communication\ValueObjects\MessageParticipant;
use App\Domain\Communication\ValueObjects\RecordContext;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\WhatsApp\Repositories\WhatsappConversationRepositoryInterface;
use Illuminate\Support\Facades\DB;

class WhatsAppChannelAdapter extends AbstractChannelAdapter
{
    public function __construct(
        private readonly WhatsappConversationRepositoryInterface $whatsappRepository,
    ) {}

    public function getChannelType(): ChannelType
    {
        return ChannelType::WHATSAPP;
    }

    public function isAvailable(): bool
    {
        // Check if WhatsApp is configured - get stats to determine availability
        $stats = $this->whatsappRepository->getStats();
        return !empty($stats);
    }

    public function getConversations(array $filters = [], int $perPage = 20, int $page = 1): PaginatedResult
    {
        return $this->whatsappRepository->list($filters, $perPage, $page);
    }

    public function getConversation(string $sourceId): ?UnifiedConversation
    {
        $conversation = $this->whatsappRepository->findByIdAsArray((int) $sourceId);

        if (!$conversation) {
            return null;
        }

        return $this->toUnifiedConversation((object) $conversation);
    }

    public function getConversationsForRecord(RecordContext $context): array
    {
        $conversations = $this->whatsappRepository->findByModuleRecord($context->moduleApiName, $context->recordId);

        return array_map(fn($c) => $this->toUnifiedConversation((object) $c), $conversations);
    }

    public function getMessages(string $sourceConversationId, int $limit = 50): array
    {
        $messages = DB::table('whatsapp_messages')
            ->where('conversation_id', $sourceConversationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(fn($m) => $this->toUnifiedMessage($m), $messages->all());
    }

    public function sendMessage(SendMessageDTO $message): UnifiedMessage
    {
        // This would integrate with WhatsApp Business API
        // For now, create the message record
        $messageId = DB::table('whatsapp_messages')->insertGetId([
            'conversation_id' => $message->conversationId,
            'direction' => 'outbound',
            'message_type' => 'text',
            'content' => $message->content,
            'sender_id' => $message->sender->userId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $whatsappMessage = DB::table('whatsapp_messages')->where('id', $messageId)->first();

        return $this->toUnifiedMessage($whatsappMessage);
    }

    public function toUnifiedConversation(mixed $sourceConversation): UnifiedConversation
    {
        $conversation = $sourceConversation;

        $contact = MessageParticipant::fromPhone(
            phone: $conversation->phone_number ?? '',
            name: $conversation->contact_name,
        );

        if ($conversation->record_id && $conversation->module_api_name) {
            $contact = MessageParticipant::fromContact(
                name: $conversation->contact_name ?? '',
                email: null,
                phone: $conversation->phone_number,
                recordContext: new RecordContext(
                    $conversation->module_api_name,
                    $conversation->record_id
                ),
            );
        }

        $linkedRecord = $conversation->record_id && $conversation->module_api_name
            ? new RecordContext($conversation->module_api_name, $conversation->record_id)
            : null;

        return UnifiedConversation::reconstitute(
            id: $conversation->id,
            channel: ChannelType::WHATSAPP,
            status: $this->mapStatus($conversation->status ?? 'open'),
            subject: null, // WhatsApp doesn't have subjects
            contact: $contact,
            assignedTo: $conversation->assigned_to,
            linkedRecord: $linkedRecord,
            sourceConversationId: (string) $conversation->id,
            externalThreadId: $conversation->wa_conversation_id,
            tags: $conversation->tags ?? [],
            messageCount: $conversation->message_count ?? 0,
            lastMessageAt: $conversation->last_message_at
                ? new \DateTimeImmutable($conversation->last_message_at)
                : null,
            firstResponseAt: null,
            responseTimeSeconds: null,
            metadata: [
                'wa_conversation_id' => $conversation->wa_conversation_id,
                'connection_id' => $conversation->connection_id,
            ],
            createdAt: new \DateTimeImmutable($conversation->created_at),
            updatedAt: $conversation->updated_at
                ? new \DateTimeImmutable($conversation->updated_at)
                : null,
        );
    }

    public function toUnifiedMessage(mixed $sourceMessage): UnifiedMessage
    {
        $message = $sourceMessage;

        $direction = $this->mapDirection($message->direction ?? 'inbound');

        $sender = $direction->isInbound()
            ? MessageParticipant::fromPhone(
                phone: $message->from_phone ?? '',
                name: $message->contact_name,
            )
            : MessageParticipant::fromUser(
                userId: $message->sender_id ?? 0,
                name: $message->sender_name ?? 'Agent',
            );

        return UnifiedMessage::reconstitute(
            id: $message->id,
            conversationId: $message->conversation_id,
            channel: ChannelType::WHATSAPP,
            direction: $direction,
            content: $message->content ?? $message->body,
            htmlContent: null,
            sender: $sender,
            recipients: [],
            attachments: $message->media ?? [],
            sourceMessageId: (string) $message->id,
            externalMessageId: $message->wa_message_id,
            status: $this->mapMessageStatus($message->status ?? 'sent'),
            sentAt: $message->sent_at ? new \DateTimeImmutable($message->sent_at) : null,
            deliveredAt: $message->delivered_at ? new \DateTimeImmutable($message->delivered_at) : null,
            readAt: $message->read_at ? new \DateTimeImmutable($message->read_at) : null,
            metadata: [
                'message_type' => $message->message_type,
                'wa_message_id' => $message->wa_message_id,
            ],
            createdAt: new \DateTimeImmutable($message->created_at),
        );
    }

    public function sync(?int $userId = null): int
    {
        // WhatsApp sync is handled by webhooks
        return 0;
    }

    private function mapMessageStatus(string $status): string
    {
        return match (strtolower($status)) {
            'pending', 'queued' => UnifiedMessage::STATUS_PENDING,
            'sending' => UnifiedMessage::STATUS_SENDING,
            'sent' => UnifiedMessage::STATUS_SENT,
            'delivered' => UnifiedMessage::STATUS_DELIVERED,
            'read' => UnifiedMessage::STATUS_READ,
            'failed', 'error' => UnifiedMessage::STATUS_FAILED,
            default => UnifiedMessage::STATUS_SENT,
        };
    }
}
