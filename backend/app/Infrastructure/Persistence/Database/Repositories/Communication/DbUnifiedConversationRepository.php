<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Communication;

use App\Domain\Communication\Entities\UnifiedConversation;
use App\Domain\Communication\Entities\UnifiedMessage;
use App\Domain\Communication\Repositories\UnifiedConversationRepositoryInterface;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\ConversationStatus;
use App\Domain\Communication\ValueObjects\MessageDirection;
use App\Domain\Communication\ValueObjects\MessageParticipant;
use App\Domain\Communication\ValueObjects\RecordContext;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbUnifiedConversationRepository implements UnifiedConversationRepositoryInterface
{
    private const TABLE_CONVERSATIONS = 'unified_conversations';
    private const TABLE_MESSAGES = 'unified_messages';

    public function findById(int $id): ?UnifiedConversation
    {
        $row = DB::table(self::TABLE_CONVERSATIONS)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toEntity($row);
    }

    public function findBySourceId(ChannelType $channel, string $sourceId): ?UnifiedConversation
    {
        $row = DB::table(self::TABLE_CONVERSATIONS)
            ->where('channel', $channel->value)
            ->where('source_conversation_id', $sourceId)
            ->whereNull('deleted_at')
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toEntity($row);
    }

    public function list(array $filters, int $perPage, int $page): PaginatedResult
    {
        $query = DB::table(self::TABLE_CONVERSATIONS)
            ->whereNull('deleted_at');

        // Apply filters
        if (!empty($filters['channel']) && $filters['channel'] !== 'all') {
            $query->where('channel', $filters['channel']);
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $query->whereNull('assigned_to');
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['linked_module']) && !empty($filters['linked_record_id'])) {
            $query->where('linked_module_api_name', $filters['linked_module'])
                ->where('linked_record_id', $filters['linked_record_id']);
        }

        // Get total count
        $total = $query->count();

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'last_message_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $offset = ($page - 1) * $perPage;
        $rows = $query->offset($offset)->limit($perPage)->get();

        $items = array_map(fn($row) => $this->toEntity($row), $rows->all());

        return new PaginatedResult(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function getByRecordContext(RecordContext $context): array
    {
        $rows = DB::table(self::TABLE_CONVERSATIONS)
            ->where('linked_module_api_name', $context->moduleApiName)
            ->where('linked_record_id', $context->recordId)
            ->whereNull('deleted_at')
            ->get();

        return array_map(fn($row) => $this->toEntity($row), $rows->all());
    }

    public function getByAssignee(int $userId, array $filters = []): array
    {
        $query = DB::table(self::TABLE_CONVERSATIONS)
            ->where('assigned_to', $userId)
            ->whereNull('deleted_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $rows = $query->get();

        return array_map(fn($row) => $this->toEntity($row), $rows->all());
    }

    public function save(UnifiedConversation $conversation): UnifiedConversation
    {
        $data = $this->toArray($conversation);

        if ($conversation->getId()) {
            // Update
            $data['updated_at'] = now()->toDateTimeString();

            DB::table(self::TABLE_CONVERSATIONS)
                ->where('id', $conversation->getId())
                ->update($data);

            $id = $conversation->getId();
        } else {
            // Insert
            $now = now()->toDateTimeString();
            $data['created_at'] = $now;
            $data['updated_at'] = $now;

            $id = DB::table(self::TABLE_CONVERSATIONS)->insertGetId($data);
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $affected = DB::table(self::TABLE_CONVERSATIONS)
            ->where('id', $id)
            ->update([
                'deleted_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

        return $affected > 0;
    }

    public function getMessages(int $conversationId, int $limit = 50, int $offset = 0): array
    {
        $rows = DB::table(self::TABLE_MESSAGES)
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return array_map(fn($row) => $this->toMessageEntity($row), $rows->all());
    }

    public function saveMessage(UnifiedMessage $message): UnifiedMessage
    {
        $data = $this->messageToArray($message);

        if ($message->getId()) {
            // Update
            $data['updated_at'] = now()->toDateTimeString();

            DB::table(self::TABLE_MESSAGES)
                ->where('id', $message->getId())
                ->update($data);

            $id = $message->getId();
        } else {
            // Insert
            $now = now()->toDateTimeString();
            $data['created_at'] = $now;
            $data['updated_at'] = $now;

            $id = DB::table(self::TABLE_MESSAGES)->insertGetId($data);
        }

        return $this->findMessageById($id);
    }

    public function findMessageById(int $id): ?UnifiedMessage
    {
        $row = DB::table(self::TABLE_MESSAGES)
            ->where('id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toMessageEntity($row);
    }

    public function findMessageBySourceId(ChannelType $channel, string $sourceId): ?UnifiedMessage
    {
        $row = DB::table(self::TABLE_MESSAGES)
            ->where('channel', $channel->value)
            ->where('source_message_id', $sourceId)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toMessageEntity($row);
    }

    public function getStats(array $filters = []): array
    {
        $baseQuery = DB::table(self::TABLE_CONVERSATIONS)
            ->whereNull('deleted_at');

        if (!empty($filters['channel'])) {
            $baseQuery->where('channel', $filters['channel']);
        }

        $total = (clone $baseQuery)->count();
        $open = (clone $baseQuery)->where('status', 'open')->count();
        $pending = (clone $baseQuery)->where('status', 'pending')->count();
        $resolved = (clone $baseQuery)->where('status', 'resolved')->count();
        $unassigned = (clone $baseQuery)
            ->whereNull('assigned_to')
            ->whereIn('status', ['open', 'pending'])
            ->count();

        $avgResponseTime = DB::table(self::TABLE_CONVERSATIONS)
            ->whereNull('deleted_at')
            ->whereNotNull('response_time_seconds')
            ->avg('response_time_seconds');

        return [
            'total' => $total,
            'open' => $open,
            'pending' => $pending,
            'resolved' => $resolved,
            'unassigned' => $unassigned,
            'avg_response_time_seconds' => (int) $avgResponseTime,
        ];
    }

    public function getCountByStatus(array $filters = []): array
    {
        $query = DB::table(self::TABLE_CONVERSATIONS)
            ->selectRaw('status, COUNT(*) as count')
            ->whereNull('deleted_at');

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        $results = $query->groupBy('status')->get();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row->status] = $row->count;
        }

        return $counts;
    }

    public function getUnreadCount(?int $userId = null): int
    {
        $query = DB::table(self::TABLE_CONVERSATIONS)
            ->whereNull('deleted_at')
            ->whereIn('status', ['open', 'pending']);

        if ($userId) {
            $query->where('assigned_to', $userId);
        }

        return $query->count();
    }

    private function toEntity(stdClass $row): UnifiedConversation
    {
        $tags = $row->tags ? json_decode($row->tags, true) : [];
        $metadata = $row->metadata ? json_decode($row->metadata, true) : [];

        $contact = MessageParticipant::fromContact(
            name: $row->contact_name ?? '',
            email: $row->contact_email,
            phone: $row->contact_phone,
            recordContext: $row->contact_record_id
                ? new RecordContext($row->contact_module_api_name ?? 'contacts', $row->contact_record_id)
                : null,
        );

        $linkedRecord = $row->linked_record_id
            ? new RecordContext($row->linked_module_api_name, $row->linked_record_id)
            : null;

        return UnifiedConversation::reconstitute(
            id: $row->id,
            channel: ChannelType::from($row->channel),
            status: ConversationStatus::from($row->status),
            subject: $row->subject,
            contact: $contact,
            assignedTo: $row->assigned_to,
            linkedRecord: $linkedRecord,
            sourceConversationId: $row->source_conversation_id,
            externalThreadId: $row->external_thread_id,
            tags: $tags,
            messageCount: $row->message_count ?? 0,
            lastMessageAt: $row->last_message_at
                ? new \DateTimeImmutable($row->last_message_at)
                : null,
            firstResponseAt: $row->first_response_at
                ? new \DateTimeImmutable($row->first_response_at)
                : null,
            responseTimeSeconds: $row->response_time_seconds,
            metadata: $metadata,
            createdAt: new \DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at
                ? new \DateTimeImmutable($row->updated_at)
                : null,
        );
    }

    private function toArray(UnifiedConversation $entity): array
    {
        $contact = $entity->getContact();
        $linkedRecord = $entity->getLinkedRecord();

        return [
            'channel' => $entity->getChannel()->value,
            'status' => $entity->getStatus()->value,
            'subject' => $entity->getSubject(),
            'contact_name' => $contact->name,
            'contact_email' => $contact->email,
            'contact_phone' => $contact->phone,
            'contact_record_id' => $contact->recordContext?->recordId,
            'contact_module_api_name' => $contact->recordContext?->moduleApiName,
            'assigned_to' => $entity->getAssignedTo(),
            'linked_module_api_name' => $linkedRecord?->moduleApiName,
            'linked_record_id' => $linkedRecord?->recordId,
            'source_conversation_id' => $entity->getSourceConversationId(),
            'external_thread_id' => $entity->getExternalThreadId(),
            'tags' => json_encode($entity->getTags()),
            'metadata' => json_encode($entity->getMetadata()),
            'message_count' => $entity->getMessageCount(),
            'last_message_at' => $entity->getLastMessageAt()?->format('Y-m-d H:i:s'),
            'first_response_at' => $entity->getFirstResponseAt()?->format('Y-m-d H:i:s'),
            'response_time_seconds' => $entity->getResponseTimeSeconds(),
        ];
    }

    private function toMessageEntity(stdClass $row): UnifiedMessage
    {
        $recipients = $row->recipients ? json_decode($row->recipients, true) : [];
        $attachments = $row->attachments ? json_decode($row->attachments, true) : [];
        $metadata = $row->metadata ? json_decode($row->metadata, true) : [];

        $sender = $row->sender_user_id
            ? MessageParticipant::fromUser(
                userId: $row->sender_user_id,
                name: $row->sender_name ?? 'Agent',
                email: $row->sender_email,
            )
            : MessageParticipant::fromContact(
                name: $row->sender_name ?? '',
                email: $row->sender_email,
                phone: $row->sender_phone,
            );

        return UnifiedMessage::reconstitute(
            id: $row->id,
            conversationId: $row->conversation_id,
            channel: ChannelType::from($row->channel),
            direction: MessageDirection::from($row->direction),
            content: $row->content,
            htmlContent: $row->html_content,
            sender: $sender,
            recipients: $recipients,
            attachments: $attachments,
            sourceMessageId: $row->source_message_id,
            externalMessageId: $row->external_message_id,
            status: $row->status,
            sentAt: $row->sent_at
                ? new \DateTimeImmutable($row->sent_at)
                : null,
            deliveredAt: $row->delivered_at
                ? new \DateTimeImmutable($row->delivered_at)
                : null,
            readAt: $row->read_at
                ? new \DateTimeImmutable($row->read_at)
                : null,
            metadata: $metadata,
            createdAt: new \DateTimeImmutable($row->created_at),
        );
    }

    private function messageToArray(UnifiedMessage $message): array
    {
        $sender = $message->getSender();

        return [
            'conversation_id' => $message->getConversationId(),
            'channel' => $message->getChannelType()->value,
            'direction' => $message->getDirection()->value,
            'content' => $message->getContent(),
            'html_content' => $message->getHtmlContent(),
            'sender_user_id' => $sender->userId,
            'sender_name' => $sender->name,
            'sender_email' => $sender->email,
            'sender_phone' => $sender->phone,
            'recipients' => json_encode(array_map(fn($r) => $r->toArray(), $message->getRecipients())),
            'attachments' => json_encode($message->getAttachments()),
            'source_message_id' => $message->getSourceMessageId(),
            'external_message_id' => $message->getExternalMessageId(),
            'status' => $message->getStatus(),
            'sent_at' => $message->getSentAt()?->format('Y-m-d H:i:s'),
            'delivered_at' => $message->getDeliveredAt()?->format('Y-m-d H:i:s'),
            'read_at' => $message->getReadAt()?->format('Y-m-d H:i:s'),
            'metadata' => json_encode($message->getMetadata()),
        ];
    }
}
