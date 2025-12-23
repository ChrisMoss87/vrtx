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
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use App\Models\SmsConnection;
use App\Models\SmsMessage;

class SmsChannelAdapter extends AbstractChannelAdapter
{
    public function __construct(
        private readonly SmsMessageRepositoryInterface $smsRepository,
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
        // SMS doesn't have native conversations - group by phone number
        $query = SmsMessage::select('to_number as phone_number')
            ->selectRaw('MAX(id) as latest_message_id')
            ->selectRaw('COUNT(*) as message_count')
            ->selectRaw('MAX(created_at) as last_message_at')
            ->groupBy('to_number')
            ->orderByDesc('last_message_at');

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $conversations = collect($paginated->items())->map(function ($item) {
            return $this->createSmsConversation($item);
        });

        return new PaginatedResult(
            items: $conversations->all(),
            total: $paginated->total(),
            perPage: $paginated->perPage(),
            currentPage: $paginated->currentPage(),
        );
    }

    public function getConversation(string $sourceId): ?UnifiedConversation
    {
        // sourceId is the phone number for SMS
        $latestMessage = SmsMessage::where('to_number', $sourceId)
            ->orWhere('from_number', $sourceId)
            ->latest()
            ->first();

        if (!$latestMessage) {
            return null;
        }

        return $this->createSmsConversation((object) [
            'phone_number' => $sourceId,
            'message_count' => SmsMessage::where('to_number', $sourceId)
                ->orWhere('from_number', $sourceId)
                ->count(),
            'last_message_at' => $latestMessage->created_at,
        ]);
    }

    public function getConversationsForRecord(RecordContext $context): array
    {
        $messages = SmsMessage::where('module_api_name', $context->moduleApiName)
            ->where('module_record_id', $context->recordId)
            ->get();

        // Group by phone number
        $grouped = $messages->groupBy(fn($m) => $m->to_number ?: $m->from_number);

        return $grouped->map(function ($msgs, $phone) {
            return $this->createSmsConversation((object) [
                'phone_number' => $phone,
                'message_count' => $msgs->count(),
                'last_message_at' => $msgs->max('created_at'),
            ]);
        })->values()->all();
    }

    public function getMessages(string $sourceConversationId, int $limit = 50): array
    {
        // sourceConversationId is phone number
        $messages = SmsMessage::where('to_number', $sourceConversationId)
            ->orWhere('from_number', $sourceConversationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $messages->map(fn($m) => $this->toUnifiedMessage($m))->all();
    }

    public function sendMessage(SendMessageDTO $message): UnifiedMessage
    {
        $smsMessage = SmsMessage::create([
            'connection_id' => SmsConnection::where('is_active', true)->first()?->id,
            'to_number' => $message->recipients[0]->phone ?? '',
            'from_number' => $message->metadata['from_number'] ?? null,
            'content' => $message->content,
            'direction' => 'outbound',
            'status' => 'pending',
            'user_id' => $message->sender->userId,
            'module_api_name' => $message->recordContext?->moduleApiName,
            'module_record_id' => $message->recordContext?->recordId,
        ]);

        return $this->toUnifiedMessage($smsMessage);
    }

    public function toUnifiedConversation(mixed $sourceConversation): UnifiedConversation
    {
        return $this->createSmsConversation($sourceConversation);
    }

    public function toUnifiedMessage(mixed $sourceMessage): UnifiedMessage
    {
        $message = $sourceMessage;

        $direction = $this->mapDirection($message->direction ?? 'outbound');

        $sender = $direction->isInbound()
            ? MessageParticipant::fromPhone(
                phone: $message->from_number ?? '',
            )
            : MessageParticipant::fromUser(
                userId: $message->user_id ?? 0,
                name: 'Agent',
            );

        $recipients = $direction->isOutbound()
            ? [MessageParticipant::fromPhone(phone: $message->to_number ?? '')]
            : [];

        return UnifiedMessage::reconstitute(
            id: $message->id,
            conversationId: 0, // SMS doesn't have real conversations
            channel: ChannelType::SMS,
            direction: $direction,
            content: $message->content ?? $message->body,
            htmlContent: null,
            sender: $sender,
            recipients: $recipients,
            attachments: $message->media ?? [],
            sourceMessageId: (string) $message->id,
            externalMessageId: $message->provider_message_id,
            status: $this->mapSmsStatus($message->status ?? 'sent'),
            sentAt: $message->sent_at ? new \DateTimeImmutable($message->sent_at) : null,
            deliveredAt: $message->delivered_at ? new \DateTimeImmutable($message->delivered_at) : null,
            readAt: null, // SMS doesn't have read receipts
            metadata: [
                'provider' => $message->provider,
                'segments' => $message->segments,
                'cost' => $message->cost,
            ],
            createdAt: new \DateTimeImmutable($message->created_at),
        );
    }

    public function sync(?int $userId = null): int
    {
        // SMS sync is handled by webhooks
        return 0;
    }

    private function createSmsConversation(object $data): UnifiedConversation
    {
        $phone = $data->phone_number;

        $contact = MessageParticipant::fromPhone(phone: $phone);

        return UnifiedConversation::create(
            channel: ChannelType::SMS,
            contact: $contact,
            subject: "SMS with {$phone}",
            sourceConversationId: $phone,
        );
    }

    private function mapSmsStatus(string $status): string
    {
        return match (strtolower($status)) {
            'pending', 'queued' => UnifiedMessage::STATUS_PENDING,
            'sending' => UnifiedMessage::STATUS_SENDING,
            'sent' => UnifiedMessage::STATUS_SENT,
            'delivered' => UnifiedMessage::STATUS_DELIVERED,
            'failed', 'error', 'undelivered' => UnifiedMessage::STATUS_FAILED,
            default => UnifiedMessage::STATUS_SENT,
        };
    }
}
