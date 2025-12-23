<?php

declare(strict_types=1);

namespace App\Application\Services\Communication;

use App\Domain\Communication\Contracts\SendMessageDTO;
use App\Domain\Communication\Entities\UnifiedConversation;
use App\Domain\Communication\Entities\UnifiedMessage;
use App\Domain\Communication\Repositories\UnifiedConversationRepositoryInterface;
use App\Domain\Communication\Services\CommunicationAggregatorService;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\ConversationStatus;
use App\Domain\Communication\ValueObjects\MessageParticipant;
use App\Domain\Communication\ValueObjects\RecordContext;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class UnifiedInboxApplicationService
{
    public function __construct(
        private readonly UnifiedConversationRepositoryInterface $repository,
        private readonly CommunicationAggregatorService $aggregator,
    ) {}

    /**
     * List conversations with filters.
     */
    public function listConversations(array $filters = [], int $perPage = 20, int $page = 1): array
    {
        $result = $this->aggregator->getUnifiedInbox($filters, $perPage, $page);

        return [
            'data' => array_map(fn($c) => $this->formatConversation($c), $result->items),
            'meta' => [
                'total' => $result->total,
                'per_page' => $result->perPage,
                'current_page' => $result->currentPage,
                'last_page' => (int) ceil($result->total / $result->perPage),
            ],
        ];
    }

    /**
     * Get a single conversation with messages.
     */
    public function getConversation(int $id): ?array
    {
        $conversation = $this->repository->findById($id);

        if (!$conversation) {
            return null;
        }

        $messages = $this->aggregator->getMessages($id, 50);

        return [
            'conversation' => $this->formatConversation($conversation),
            'messages' => array_map(fn($m) => $this->formatMessage($m), $messages),
        ];
    }

    /**
     * Get conversations for a CRM record.
     */
    public function getConversationsForRecord(string $moduleApiName, int $recordId): array
    {
        $context = new RecordContext($moduleApiName, $recordId);
        $conversations = $this->aggregator->getConversationsForRecord($context);

        return array_map(fn($c) => $this->formatConversation($c), $conversations);
    }

    /**
     * Reply to a conversation.
     */
    public function replyToConversation(int $conversationId, array $data): array
    {
        $message = $this->aggregator->reply(
            conversationId: $conversationId,
            content: $data['content'],
            htmlContent: $data['html_content'] ?? null,
            attachments: $data['attachments'] ?? [],
        );

        return $this->formatMessage($message);
    }

    /**
     * Assign a conversation to a user.
     */
    public function assignConversation(int $conversationId, int $userId): array
    {
        $conversation = $this->aggregator->assignConversation($conversationId, $userId);

        return $this->formatConversation($conversation);
    }

    /**
     * Unassign a conversation.
     */
    public function unassignConversation(int $conversationId): array
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        $conversation->unassign();
        $saved = $this->repository->save($conversation);

        return $this->formatConversation($saved);
    }

    /**
     * Update conversation status.
     */
    public function updateStatus(int $conversationId, string $status): array
    {
        $conversation = $this->aggregator->updateStatus($conversationId, $status);

        return $this->formatConversation($conversation);
    }

    /**
     * Resolve a conversation.
     */
    public function resolveConversation(int $conversationId): array
    {
        return $this->updateStatus($conversationId, 'resolved');
    }

    /**
     * Close a conversation.
     */
    public function closeConversation(int $conversationId): array
    {
        return $this->updateStatus($conversationId, 'closed');
    }

    /**
     * Reopen a conversation.
     */
    public function reopenConversation(int $conversationId): array
    {
        return $this->updateStatus($conversationId, 'open');
    }

    /**
     * Link conversation to a CRM record.
     */
    public function linkToRecord(int $conversationId, string $moduleApiName, int $recordId): array
    {
        $context = new RecordContext($moduleApiName, $recordId);
        $conversation = $this->aggregator->linkToRecord($conversationId, $context);

        return $this->formatConversation($conversation);
    }

    /**
     * Unlink conversation from CRM record.
     */
    public function unlinkRecord(int $conversationId): array
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        $conversation->unlinkRecord();
        $saved = $this->repository->save($conversation);

        return $this->formatConversation($saved);
    }

    /**
     * Add tag to conversation.
     */
    public function addTag(int $conversationId, string $tag): array
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        $conversation->addTag($tag);
        $saved = $this->repository->save($conversation);

        return $this->formatConversation($saved);
    }

    /**
     * Remove tag from conversation.
     */
    public function removeTag(int $conversationId, string $tag): array
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException('Conversation not found');
        }

        $conversation->removeTag($tag);
        $saved = $this->repository->save($conversation);

        return $this->formatConversation($saved);
    }

    /**
     * Get inbox statistics.
     */
    public function getStats(array $filters = []): array
    {
        return $this->aggregator->getStats($filters);
    }

    /**
     * Get count by status.
     */
    public function getCountByStatus(array $filters = []): array
    {
        return $this->repository->getCountByStatus($filters);
    }

    /**
     * Get available channels.
     */
    public function getAvailableChannels(): array
    {
        $channels = $this->aggregator->getAvailableChannels();

        return array_map(fn($c) => [
            'id' => $c->value,
            'name' => $c->label(),
            'icon' => $c->icon(),
            'color' => $c->color(),
        ], $channels);
    }

    /**
     * Sync all channels.
     */
    public function sync(): array
    {
        return $this->aggregator->syncAll();
    }

    /**
     * Format conversation for API response.
     */
    private function formatConversation(UnifiedConversation $conversation): array
    {
        $contact = $conversation->getContact();
        $linkedRecord = $conversation->getLinkedRecord();

        return [
            'id' => $conversation->getId(),
            'channel' => [
                'type' => $conversation->getChannel()->value,
                'label' => $conversation->getChannel()->label(),
                'icon' => $conversation->getChannel()->icon(),
                'color' => $conversation->getChannel()->color(),
            ],
            'status' => $conversation->getStatus()->value,
            'subject' => $conversation->getSubject(),
            'contact' => [
                'name' => $contact->getDisplayName(),
                'email' => $contact->email,
                'phone' => $contact->phone,
                'user_id' => $contact->userId,
                'record_context' => $contact->recordContext?->toArray(),
            ],
            'assigned_to' => $conversation->getAssignedTo(),
            'linked_record' => $linkedRecord?->toArray(),
            'tags' => $conversation->getTags(),
            'message_count' => $conversation->getMessageCount(),
            'last_message_at' => $conversation->getLastMessageAt()?->format(\DateTimeInterface::ATOM),
            'first_response_at' => $conversation->getFirstResponseAt()?->format(\DateTimeInterface::ATOM),
            'response_time_seconds' => $conversation->getResponseTimeSeconds(),
            'created_at' => $conversation->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $conversation->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Format message for API response.
     */
    private function formatMessage(UnifiedMessage $message): array
    {
        $sender = $message->getSender();

        return [
            'id' => $message->getId(),
            'conversation_id' => $message->getConversationId(),
            'channel' => $message->getChannelType()->value,
            'direction' => $message->getDirection()->value,
            'content' => $message->getContent(),
            'html_content' => $message->getHtmlContent(),
            'sender' => [
                'user_id' => $sender->userId,
                'name' => $sender->getDisplayName(),
                'email' => $sender->email,
                'phone' => $sender->phone,
            ],
            'recipients' => array_map(fn($r) => $r->toArray(), $message->getRecipients()),
            'attachments' => $message->getAttachments(),
            'status' => $message->getStatus(),
            'sent_at' => $message->getSentAt()?->format(\DateTimeInterface::ATOM),
            'delivered_at' => $message->getDeliveredAt()?->format(\DateTimeInterface::ATOM),
            'read_at' => $message->getReadAt()?->format(\DateTimeInterface::ATOM),
            'created_at' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
