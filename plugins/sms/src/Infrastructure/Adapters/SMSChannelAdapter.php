<?php

declare(strict_types=1);

namespace Plugins\SMS\Infrastructure\Adapters;

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
use App\Models\SmsConnection;
use Plugins\SMS\Application\Services\SMSApplicationService;
use Plugins\SMS\Domain\Repositories\SMSRepositoryInterface;

/**
 * SMS channel adapter for the unified communication system.
 * This is loaded by the SMS plugin when active.
 */
class SMSChannelAdapter implements CommunicationChannelInterface
{
    public function __construct(
        private readonly SMSApplicationService $smsService,
        private readonly SMSRepositoryInterface $repository,
    ) {}

    public function getChannelType(): ChannelType
    {
        return ChannelType::SMS;
    }

    public function isAvailable(): bool
    {
        return SmsConnection::where('is_active', true)->exists();
    }

    public function getConversations(array $filters = [], int $perPage = 20, int $page = 1): PaginatedResult
    {
        // SMS doesn't have traditional conversations, group by phone number
        $messages = $this->repository->listMessages(['direction' => 'all'], 1000);

        $conversations = collect($messages['data'] ?? [])
            ->groupBy(fn($m) => $m['from_number'] === $m['to_number'] ? $m['from_number'] :
                ($m['direction'] === 'inbound' ? $m['from_number'] : $m['to_number']))
            ->map(function ($msgs, $phone) {
                $latest = collect($msgs)->sortByDesc('created_at')->first();
                return $this->toUnifiedConversation((object) [
                    'id' => md5($phone),
                    'phone_number' => $phone,
                    'messages' => $msgs,
                    'last_message_at' => $latest['created_at'] ?? null,
                    'message_count' => count($msgs),
                    'status' => 'open',
                ]);
            })
            ->values()
            ->slice(($page - 1) * $perPage, $perPage)
            ->all();

        return new PaginatedResult(
            items: $conversations,
            total: count($conversations),
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function getConversation(string $sourceId): ?UnifiedConversation
    {
        $messages = $this->smsService->getConversation($sourceId, 100);

        if (empty($messages)) {
            return null;
        }

        $latest = collect($messages)->sortByDesc('created_at')->first();

        return $this->toUnifiedConversation((object) [
            'id' => md5($sourceId),
            'phone_number' => $sourceId,
            'messages' => $messages,
            'last_message_at' => $latest['created_at'] ?? null,
            'message_count' => count($messages),
            'status' => 'open',
        ]);
    }

    public function getConversationsForRecord(RecordContext $context): array
    {
        $messages = $this->smsService->getRecordMessages(
            $context->moduleApiName,
            $context->recordId
        );

        // Group by phone number
        $conversations = collect($messages)
            ->groupBy(fn($m) => $m['direction'] === 'inbound' ? $m['from_number'] : $m['to_number'])
            ->map(function ($msgs, $phone) use ($context) {
                $latest = collect($msgs)->sortByDesc('created_at')->first();
                return $this->toUnifiedConversation((object) [
                    'id' => md5($phone),
                    'phone_number' => $phone,
                    'messages' => $msgs,
                    'last_message_at' => $latest['created_at'] ?? null,
                    'message_count' => count($msgs),
                    'status' => 'open',
                    'module_api_name' => $context->moduleApiName,
                    'record_id' => $context->recordId,
                ]);
            })
            ->values()
            ->all();

        return $conversations;
    }

    public function getMessages(string $sourceConversationId, int $limit = 50): array
    {
        $messages = $this->smsService->getConversation($sourceConversationId, $limit);

        return collect($messages)
            ->map(fn($m) => $this->toUnifiedMessage((object) $m))
            ->all();
    }

    public function sendMessage(SendMessageDTO $message): UnifiedMessage
    {
        // Get active connection
        $connections = $this->repository->listConnections(true);
        $connectionId = $connections[0]['id'] ?? null;

        if (!$connectionId) {
            throw new \RuntimeException('No active SMS connection available');
        }

        $result = $this->smsService->sendSms(
            $message->conversationId, // Phone number for SMS
            $message->content,
            $connectionId
        );

        return $this->toUnifiedMessage((object) $result);
    }

    public function toUnifiedConversation(mixed $source): UnifiedConversation
    {
        $phoneNumber = $source->phone_number ?? '';

        $contact = MessageParticipant::fromPhone(
            phone: $phoneNumber,
            name: null,
        );

        $linkedRecordModule = $source->module_api_name ?? null;
        $linkedRecordId = $source->record_id ?? null;

        if ($linkedRecordId && $linkedRecordModule) {
            $contact = MessageParticipant::fromContact(
                name: '',
                email: null,
                phone: $phoneNumber,
                recordContext: new RecordContext($linkedRecordModule, $linkedRecordId),
            );
        }

        $linkedRecord = $linkedRecordId && $linkedRecordModule
            ? new RecordContext($linkedRecordModule, $linkedRecordId)
            : null;

        return UnifiedConversation::reconstitute(
            id: is_numeric($source->id) ? $source->id : crc32($source->id),
            channel: ChannelType::SMS,
            status: $this->mapStatus($source->status ?? 'open'),
            subject: null,
            contact: $contact,
            assignedTo: null,
            linkedRecord: $linkedRecord,
            sourceConversationId: $phoneNumber,
            externalThreadId: null,
            tags: [],
            messageCount: $source->message_count ?? 0,
            lastMessageAt: isset($source->last_message_at)
                ? new \DateTimeImmutable($source->last_message_at)
                : null,
            firstResponseAt: null,
            responseTimeSeconds: null,
            metadata: [],
            createdAt: isset($source->created_at)
                ? new \DateTimeImmutable($source->created_at)
                : new \DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public function toUnifiedMessage(mixed $source): UnifiedMessage
    {
        $direction = $this->mapDirection($source->direction ?? 'outbound');

        $sender = $direction->isInbound()
            ? MessageParticipant::fromPhone(
                phone: $source->from_number ?? '',
                name: null,
            )
            : MessageParticipant::fromUser(
                userId: $source->sent_by ?? 0,
                name: 'System',
            );

        return UnifiedMessage::reconstitute(
            id: $source->id,
            conversationId: md5($source->from_number ?? $source->to_number ?? ''),
            channel: ChannelType::SMS,
            direction: $direction,
            content: $source->content ?? '',
            htmlContent: null,
            sender: $sender,
            recipients: [MessageParticipant::fromPhone($source->to_number ?? '', null)],
            attachments: [],
            sourceMessageId: (string) $source->id,
            externalMessageId: $source->provider_message_id ?? null,
            status: $this->mapMessageStatus($source->status ?? 'sent'),
            sentAt: isset($source->sent_at) ? new \DateTimeImmutable($source->sent_at) : null,
            deliveredAt: isset($source->delivered_at) ? new \DateTimeImmutable($source->delivered_at) : null,
            readAt: null, // SMS doesn't support read receipts
            metadata: [
                'segment_count' => $source->segment_count ?? 1,
                'cost' => $source->cost ?? null,
            ],
            createdAt: isset($source->created_at)
                ? new \DateTimeImmutable($source->created_at)
                : new \DateTimeImmutable(),
        );
    }

    public function sync(?int $userId = null): int
    {
        // SMS sync is handled via webhooks
        return 0;
    }

    protected function mapStatus(string $status): ConversationStatus
    {
        return match (strtolower($status)) {
            'open', 'active' => ConversationStatus::OPEN,
            'pending' => ConversationStatus::PENDING,
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
            'failed', 'undelivered' => UnifiedMessage::STATUS_FAILED,
            default => UnifiedMessage::STATUS_SENT,
        };
    }
}
