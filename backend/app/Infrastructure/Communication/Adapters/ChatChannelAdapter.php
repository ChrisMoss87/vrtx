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
use App\Domain\Chat\Repositories\ChatConversationRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ChatChannelAdapter extends AbstractChannelAdapter
{
    public function __construct(
        private readonly ChatConversationRepositoryInterface $chatRepository,
    ) {}

    public function getChannelType(): ChannelType
    {
        return ChannelType::CHAT;
    }

    public function isAvailable(): bool
    {
        // Check if chat widgets are configured
        return DB::table('chat_widgets')->where('is_active', true)->exists();
    }

    public function getConversations(array $filters = [], int $perPage = 20, int $page = 1): PaginatedResult
    {
        return $this->chatRepository->listConversations($filters, $perPage, $page);
    }

    public function getConversation(string $sourceId): ?UnifiedConversation
    {
        $conversation = $this->chatRepository->findByIdAsArray((int) $sourceId);

        if (!$conversation) {
            return null;
        }

        return $this->toUnifiedConversation((object) $conversation);
    }

    public function getConversationsForRecord(RecordContext $context): array
    {
        $filters = [
            'contact_module' => $context->moduleApiName,
            'contact_id' => $context->recordId,
        ];

        $result = $this->chatRepository->listConversations($filters, perPage: 1000, page: 1);

        return array_map(fn($c) => $this->toUnifiedConversation((object) $c), $result->items);
    }

    public function getMessages(string $sourceConversationId, int $limit = 50): array
    {
        $messages = DB::table('chat_messages')
            ->where('conversation_id', $sourceConversationId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(fn($m) => $this->toUnifiedMessage($m), $messages->all());
    }

    public function sendMessage(SendMessageDTO $message): UnifiedMessage
    {
        $messageId = DB::table('chat_messages')->insertGetId([
            'conversation_id' => $message->conversationId,
            'sender_type' => 'agent',
            'sender_id' => $message->sender->userId,
            'content' => $message->content,
            'attachments' => json_encode($message->attachments),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update conversation
        DB::table('chat_conversations')->where('id', $message->conversationId)->update([
            'last_message_at' => now(),
            'message_count' => DB::raw('message_count + 1'),
            'updated_at' => now(),
        ]);

        $chatMessage = DB::table('chat_messages')->where('id', $messageId)->first();

        return $this->toUnifiedMessage($chatMessage);
    }

    public function toUnifiedConversation(mixed $sourceConversation): UnifiedConversation
    {
        $conversation = $sourceConversation;

        // Get visitor info - load from database if visitor_id exists
        $visitor = null;
        if (isset($conversation->visitor_id)) {
            $visitor = DB::table('chat_visitors')->where('id', $conversation->visitor_id)->first();
        }

        $contact = MessageParticipant::fromContact(
            name: $visitor?->name ?? ($conversation->visitor_name ?? 'Visitor'),
            email: $visitor?->email ?? ($conversation->visitor_email ?? null),
            phone: $visitor?->phone ?? null,
            recordContext: isset($conversation->contact_id) && $conversation->contact_id
                ? new RecordContext($conversation->contact_module ?? 'contacts', $conversation->contact_id)
                : null,
        );

        $linkedRecord = isset($conversation->contact_id) && $conversation->contact_id
            ? new RecordContext($conversation->contact_module ?? 'contacts', $conversation->contact_id)
            : null;

        return UnifiedConversation::reconstitute(
            id: $conversation->id,
            channel: ChannelType::CHAT,
            status: $this->mapStatus($conversation->status ?? 'open'),
            subject: $conversation->subject ?? 'Live Chat',
            contact: $contact,
            assignedTo: $conversation->assigned_to,
            linkedRecord: $linkedRecord,
            sourceConversationId: (string) $conversation->id,
            externalThreadId: $conversation->session_id,
            tags: $conversation->tags ?? [],
            messageCount: $conversation->message_count ?? 0,
            lastMessageAt: $conversation->last_message_at
                ? new \DateTimeImmutable($conversation->last_message_at)
                : null,
            firstResponseAt: $conversation->first_response_at
                ? new \DateTimeImmutable($conversation->first_response_at)
                : null,
            responseTimeSeconds: $conversation->response_time_seconds,
            metadata: [
                'widget_id' => $conversation->widget_id,
                'page_url' => $conversation->page_url,
                'referrer' => $conversation->referrer,
                'rating' => $conversation->rating,
                'rating_comment' => $conversation->rating_comment,
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

        $isAgent = $message->sender_type === 'agent';

        if ($isAgent) {
            // Load user info if sender_id exists
            $user = isset($message->sender_id) ? DB::table('users')->where('id', $message->sender_id)->first() : null;
            $sender = MessageParticipant::fromUser(
                userId: $message->sender_id ?? 0,
                name: $user?->name ?? 'Agent',
                email: $user?->email ?? null,
            );
        } else {
            $sender = MessageParticipant::fromContact(
                name: $message->visitor_name ?? 'Visitor',
                email: $message->visitor_email ?? null,
                phone: null,
            );
        }

        $direction = $isAgent ? MessageDirection::OUTBOUND : MessageDirection::INBOUND;

        $attachments = isset($message->attachments)
            ? (is_string($message->attachments) ? json_decode($message->attachments, true) : $message->attachments)
            : [];

        return UnifiedMessage::reconstitute(
            id: $message->id,
            conversationId: $message->conversation_id,
            channel: ChannelType::CHAT,
            direction: $direction,
            content: $message->content ?? '',
            htmlContent: null,
            sender: $sender,
            recipients: [],
            attachments: $attachments ?? [],
            sourceMessageId: (string) $message->id,
            externalMessageId: null,
            status: UnifiedMessage::STATUS_DELIVERED, // Chat messages are instant
            sentAt: new \DateTimeImmutable($message->created_at),
            deliveredAt: new \DateTimeImmutable($message->created_at),
            readAt: isset($message->read_at) && $message->read_at ? new \DateTimeImmutable($message->read_at) : null,
            metadata: [
                'sender_type' => $message->sender_type ?? null,
                'is_system' => $message->is_system ?? false,
            ],
            createdAt: new \DateTimeImmutable($message->created_at),
        );
    }

    public function sync(?int $userId = null): int
    {
        // Chat is real-time, no sync needed
        return 0;
    }
}
