<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Communication;

use App\Domain\Communication\Entities\UnifiedConversation;
use App\Domain\Communication\Entities\UnifiedMessage;
use App\Domain\Communication\Repositories\UnifiedConversationRepositoryInterface;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\ConversationStatus;
use App\Domain\Communication\ValueObjects\MessageDirection;
use App\Domain\Communication\ValueObjects\MessageParticipant;
use App\Domain\Communication\ValueObjects\RecordContext;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Models\UnifiedConversation as ConversationModel;
use App\Models\UnifiedMessage as MessageModel;

class EloquentUnifiedConversationRepository implements UnifiedConversationRepositoryInterface
{
    public function findById(int $id): ?UnifiedConversation
    {
        $model = ConversationModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findBySourceId(ChannelType $channel, string $sourceId): ?UnifiedConversation
    {
        $model = ConversationModel::where('channel', $channel->value)
            ->where('source_conversation_id', $sourceId)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function list(array $filters, int $perPage, int $page): PaginatedResult
    {
        $query = ConversationModel::query();

        // Apply filters
        if (!empty($filters['channel']) && $filters['channel'] !== 'all') {
            $query->channel($filters['channel']);
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->status($filters['status']);
            }
        }

        if (!empty($filters['assigned_to'])) {
            $query->assignedTo($filters['assigned_to']);
        }

        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $query->unassigned();
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['linked_module']) && !empty($filters['linked_record_id'])) {
            $query->linkedToRecord($filters['linked_module'], $filters['linked_record_id']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'last_message_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return new PaginatedResult(
            items: collect($paginated->items())->map(fn($m) => $this->toEntity($m))->all(),
            total: $paginated->total(),
            perPage: $paginated->perPage(),
            currentPage: $paginated->currentPage(),
        );
    }

    public function getByRecordContext(RecordContext $context): array
    {
        $models = ConversationModel::linkedToRecord($context->moduleApiName, $context->recordId)->get();

        return $models->map(fn($m) => $this->toEntity($m))->all();
    }

    public function getByAssignee(int $userId, array $filters = []): array
    {
        $query = ConversationModel::assignedTo($userId);

        if (!empty($filters['status'])) {
            $query->status($filters['status']);
        }

        return $query->get()->map(fn($m) => $this->toEntity($m))->all();
    }

    public function save(UnifiedConversation $conversation): UnifiedConversation
    {
        $data = $this->toArray($conversation);

        if ($conversation->getId()) {
            $model = ConversationModel::find($conversation->getId());
            $model->update($data);
        } else {
            $model = ConversationModel::create($data);
        }

        return $this->toEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        return ConversationModel::destroy($id) > 0;
    }

    public function getMessages(int $conversationId, int $limit = 50, int $offset = 0): array
    {
        $messages = MessageModel::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return $messages->map(fn($m) => $this->toMessageEntity($m))->all();
    }

    public function saveMessage(UnifiedMessage $message): UnifiedMessage
    {
        $data = $this->messageToArray($message);

        if ($message->getId()) {
            $model = MessageModel::find($message->getId());
            $model->update($data);
        } else {
            $model = MessageModel::create($data);
        }

        return $this->toMessageEntity($model->fresh());
    }

    public function findMessageById(int $id): ?UnifiedMessage
    {
        $model = MessageModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toMessageEntity($model);
    }

    public function findMessageBySourceId(ChannelType $channel, string $sourceId): ?UnifiedMessage
    {
        $model = MessageModel::where('channel', $channel->value)
            ->where('source_message_id', $sourceId)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toMessageEntity($model);
    }

    public function getStats(array $filters = []): array
    {
        $query = ConversationModel::query();

        if (!empty($filters['channel'])) {
            $query->channel($filters['channel']);
        }

        $total = $query->count();
        $open = (clone $query)->status('open')->count();
        $pending = (clone $query)->status('pending')->count();
        $resolved = (clone $query)->status('resolved')->count();
        $unassigned = (clone $query)->unassigned()->active()->count();

        $avgResponseTime = ConversationModel::whereNotNull('response_time_seconds')
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
        $query = ConversationModel::query();

        if (!empty($filters['channel'])) {
            $query->channel($filters['channel']);
        }

        return $query->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();
    }

    public function getUnreadCount(?int $userId = null): int
    {
        $query = ConversationModel::active();

        if ($userId) {
            $query->assignedTo($userId);
        }

        return $query->count();
    }

    private function toEntity(ConversationModel $model): UnifiedConversation
    {
        $contact = MessageParticipant::fromContact(
            name: $model->contact_name ?? '',
            email: $model->contact_email,
            phone: $model->contact_phone,
            recordContext: $model->contact_record_id
                ? new RecordContext($model->contact_module_api_name ?? 'contacts', $model->contact_record_id)
                : null,
        );

        $linkedRecord = $model->linked_record_id
            ? new RecordContext($model->linked_module_api_name, $model->linked_record_id)
            : null;

        return UnifiedConversation::reconstitute(
            id: $model->id,
            channel: ChannelType::from($model->channel),
            status: ConversationStatus::from($model->status),
            subject: $model->subject,
            contact: $contact,
            assignedTo: $model->assigned_to,
            linkedRecord: $linkedRecord,
            sourceConversationId: $model->source_conversation_id,
            externalThreadId: $model->external_thread_id,
            tags: $model->tags ?? [],
            messageCount: $model->message_count ?? 0,
            lastMessageAt: $model->last_message_at
                ? new \DateTimeImmutable($model->last_message_at->toDateTimeString())
                : null,
            firstResponseAt: $model->first_response_at
                ? new \DateTimeImmutable($model->first_response_at->toDateTimeString())
                : null,
            responseTimeSeconds: $model->response_time_seconds,
            metadata: $model->metadata ?? [],
            createdAt: new \DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at
                ? new \DateTimeImmutable($model->updated_at->toDateTimeString())
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
            'tags' => $entity->getTags(),
            'metadata' => $entity->getMetadata(),
            'message_count' => $entity->getMessageCount(),
            'last_message_at' => $entity->getLastMessageAt()?->format('Y-m-d H:i:s'),
            'first_response_at' => $entity->getFirstResponseAt()?->format('Y-m-d H:i:s'),
            'response_time_seconds' => $entity->getResponseTimeSeconds(),
        ];
    }

    private function toMessageEntity(MessageModel $model): UnifiedMessage
    {
        $sender = $model->sender_user_id
            ? MessageParticipant::fromUser(
                userId: $model->sender_user_id,
                name: $model->sender_name ?? 'Agent',
                email: $model->sender_email,
            )
            : MessageParticipant::fromContact(
                name: $model->sender_name ?? '',
                email: $model->sender_email,
                phone: $model->sender_phone,
            );

        return UnifiedMessage::reconstitute(
            id: $model->id,
            conversationId: $model->conversation_id,
            channel: ChannelType::from($model->channel),
            direction: MessageDirection::from($model->direction),
            content: $model->content,
            htmlContent: $model->html_content,
            sender: $sender,
            recipients: $model->recipients ?? [],
            attachments: $model->attachments ?? [],
            sourceMessageId: $model->source_message_id,
            externalMessageId: $model->external_message_id,
            status: $model->status,
            sentAt: $model->sent_at
                ? new \DateTimeImmutable($model->sent_at->toDateTimeString())
                : null,
            deliveredAt: $model->delivered_at
                ? new \DateTimeImmutable($model->delivered_at->toDateTimeString())
                : null,
            readAt: $model->read_at
                ? new \DateTimeImmutable($model->read_at->toDateTimeString())
                : null,
            metadata: $model->metadata ?? [],
            createdAt: new \DateTimeImmutable($model->created_at->toDateTimeString()),
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
            'recipients' => array_map(fn($r) => $r->toArray(), $message->getRecipients()),
            'attachments' => $message->getAttachments(),
            'source_message_id' => $message->getSourceMessageId(),
            'external_message_id' => $message->getExternalMessageId(),
            'status' => $message->getStatus(),
            'sent_at' => $message->getSentAt()?->format('Y-m-d H:i:s'),
            'delivered_at' => $message->getDeliveredAt()?->format('Y-m-d H:i:s'),
            'read_at' => $message->getReadAt()?->format('Y-m-d H:i:s'),
            'metadata' => $message->getMetadata(),
        ];
    }
}
