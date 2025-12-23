<?php

declare(strict_types=1);

namespace Plugins\WhatsApp\Infrastructure\Adapters;

use App\Domain\Communication\Contracts\CommunicationChannelInterface;
use App\Domain\Communication\Contracts\SendMessageDTO;
use App\Domain\Communication\Entities\UnifiedConversation;
use App\Domain\Communication\Entities\UnifiedMessage;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\ConversationStatus;
use App\Domain\Communication\ValueObjects\MessageDirection;
use App\Domain\Communication\ValueObjects\MessageParticipant;
use App\Domain\Communication\ValueObjects\RecordContext;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Plugins\WhatsApp\Application\Services\WhatsAppApplicationService;

/**
 * WhatsApp channel adapter for the unified communication system.
 * This is loaded by the WhatsApp plugin when active.
 */
class WhatsAppChannelAdapter implements CommunicationChannelInterface
{
    public function __construct(
        private readonly WhatsAppApplicationService $whatsAppService,
    ) {}

    public function getChannelType(): ChannelType
    {
        return ChannelType::WHATSAPP;
    }

    public function isAvailable(): bool
    {
        return WhatsappConnection::where('status', 'active')->exists();
    }

    public function getConversations(array $filters = [], int $perPage = 20, int $page = 1): PaginatedResult
    {
        $result = $this->whatsAppService->listConversations($filters, $perPage);

        $conversations = collect($result['data'] ?? [])
            ->map(fn($c) => $this->toUnifiedConversation((object) $c))
            ->all();

        return new PaginatedResult(
            items: $conversations,
            total: $result['meta']['total'] ?? count($conversations),
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function getConversation(string $sourceId): ?UnifiedConversation
    {
        $conversation = $this->whatsAppService->getConversation((int) $sourceId);

        if (!$conversation) {
            return null;
        }

        return $this->toUnifiedConversation((object) $conversation);
    }

    public function getConversationsForRecord(RecordContext $context): array
    {
        $conversations = $this->whatsAppService->getConversationsForRecord(
            $context->moduleApiName,
            $context->recordId
        );

        return collect($conversations)
            ->map(fn($c) => $this->toUnifiedConversation((object) $c))
            ->all();
    }

    public function getMessages(string $sourceConversationId, int $limit = 50): array
    {
        $conversation = $this->whatsAppService->getConversation((int) $sourceConversationId);

        if (!$conversation) {
            return [];
        }

        return collect($conversation['messages'] ?? [])
            ->take($limit)
            ->map(fn($m) => $this->toUnifiedMessage((object) $m))
            ->all();
    }

    public function sendMessage(SendMessageDTO $message): UnifiedMessage
    {
        $conversation = $this->whatsAppService->getConversation((int) $message->conversationId);

        if (!$conversation) {
            throw new \RuntimeException('Conversation not found');
        }

        $result = $this->whatsAppService->sendTextMessage(
            $conversation['phone_number'] ?? $conversation['contact_phone'],
            $message->content,
            (int) $message->conversationId
        );

        return $this->toUnifiedMessage((object) $result);
    }

    public function toUnifiedConversation(mixed $source): UnifiedConversation
    {
        $phoneNumber = $source->phone_number ?? $source->contact_phone ?? '';
        $contactName = $source->contact_name ?? null;

        $contact = MessageParticipant::fromPhone(
            phone: $phoneNumber,
            name: $contactName,
        );

        $linkedRecordModule = $source->linked_module_api_name ?? $source->module_api_name ?? null;
        $linkedRecordId = $source->linked_record_id ?? $source->record_id ?? null;

        if ($linkedRecordId && $linkedRecordModule) {
            $contact = MessageParticipant::fromContact(
                name: $contactName ?? '',
                email: null,
                phone: $phoneNumber,
                recordContext: new RecordContext($linkedRecordModule, $linkedRecordId),
            );
        }

        $linkedRecord = $linkedRecordId && $linkedRecordModule
            ? new RecordContext($linkedRecordModule, $linkedRecordId)
            : null;

        return UnifiedConversation::reconstitute(
            id: $source->id,
            channel: ChannelType::WHATSAPP,
            status: $this->mapStatus($source->status ?? 'open'),
            subject: null,
            contact: $contact,
            assignedTo: $source->assigned_to ?? null,
            linkedRecord: $linkedRecord,
            sourceConversationId: (string) $source->id,
            externalThreadId: $source->wa_conversation_id ?? null,
            tags: $source->tags ?? [],
            messageCount: $source->message_count ?? 0,
            lastMessageAt: isset($source->last_message_at)
                ? new \DateTimeImmutable($source->last_message_at)
                : null,
            firstResponseAt: null,
            responseTimeSeconds: null,
            metadata: [
                'connection_id' => $source->connection_id ?? null,
                'contact_wa_id' => $source->contact_wa_id ?? null,
            ],
            createdAt: isset($source->created_at)
                ? new \DateTimeImmutable($source->created_at)
                : new \DateTimeImmutable(),
            updatedAt: isset($source->updated_at)
                ? new \DateTimeImmutable($source->updated_at)
                : null,
        );
    }

    public function toUnifiedMessage(mixed $source): UnifiedMessage
    {
        $direction = $this->mapDirection($source->direction ?? 'inbound');

        $sender = $direction->isInbound()
            ? MessageParticipant::fromPhone(
                phone: $source->from_phone ?? '',
                name: $source->contact_name ?? null,
            )
            : MessageParticipant::fromUser(
                userId: $source->sent_by ?? $source->sender_id ?? 0,
                name: $source->sender_name ?? 'Agent',
            );

        return UnifiedMessage::reconstitute(
            id: $source->id,
            conversationId: $source->conversation_id,
            channel: ChannelType::WHATSAPP,
            direction: $direction,
            content: $source->content ?? $source->body ?? '',
            htmlContent: null,
            sender: $sender,
            recipients: [],
            attachments: $source->media ?? [],
            sourceMessageId: (string) $source->id,
            externalMessageId: $source->whatsapp_message_id ?? $source->wa_message_id ?? null,
            status: $this->mapMessageStatus($source->status ?? 'sent'),
            sentAt: isset($source->sent_at) ? new \DateTimeImmutable($source->sent_at) : null,
            deliveredAt: isset($source->delivered_at) ? new \DateTimeImmutable($source->delivered_at) : null,
            readAt: isset($source->read_at) ? new \DateTimeImmutable($source->read_at) : null,
            metadata: [
                'type' => $source->type ?? 'text',
                'template_id' => $source->template_id ?? null,
            ],
            createdAt: isset($source->created_at)
                ? new \DateTimeImmutable($source->created_at)
                : new \DateTimeImmutable(),
        );
    }

    public function sync(?int $userId = null): int
    {
        // WhatsApp sync is handled via webhooks
        return 0;
    }

    protected function mapStatus(string $status): ConversationStatus
    {
        return match (strtolower($status)) {
            'open', 'active' => ConversationStatus::OPEN,
            'pending', 'waiting' => ConversationStatus::PENDING,
            'resolved' => ConversationStatus::RESOLVED,
            'closed' => ConversationStatus::CLOSED,
            default => ConversationStatus::OPEN,
        };
    }

    protected function mapDirection(string $direction): MessageDirection
    {
        return match (strtolower($direction)) {
            'outbound', 'out', 'sent' => MessageDirection::OUTBOUND,
            default => MessageDirection::INBOUND,
        };
    }

    protected function mapMessageStatus(string $status): string
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
