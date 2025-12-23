<?php

declare(strict_types=1);

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Contracts\CommunicationChannelInterface;
use App\Domain\Communication\Contracts\SendMessageDTO;
use App\Domain\Communication\Entities\UnifiedConversation;
use App\Domain\Communication\Entities\UnifiedMessage;
use App\Domain\Communication\Repositories\UnifiedConversationRepositoryInterface;
use App\Domain\Communication\ValueObjects\ChannelType;
use App\Domain\Communication\ValueObjects\RecordContext;
use App\Domain\Shared\ValueObjects\PaginatedResult;

class CommunicationAggregatorService
{
    /** @var array<string, CommunicationChannelInterface> */
    private array $channels = [];

    public function __construct(
        private readonly UnifiedConversationRepositoryInterface $repository,
    ) {}

    /**
     * Register a channel adapter.
     */
    public function registerChannel(CommunicationChannelInterface $channel): void
    {
        $this->channels[$channel->getChannelType()->value] = $channel;
    }

    /**
     * Get a registered channel adapter.
     */
    public function getChannel(ChannelType $type): ?CommunicationChannelInterface
    {
        return $this->channels[$type->value] ?? null;
    }

    /**
     * Get all available channel types.
     */
    public function getAvailableChannels(): array
    {
        $available = [];
        foreach ($this->channels as $channel) {
            if ($channel->isAvailable()) {
                $available[] = $channel->getChannelType();
            }
        }
        return $available;
    }

    /**
     * Get unified inbox with conversations from all channels.
     */
    public function getUnifiedInbox(array $filters = [], int $perPage = 20, int $page = 1): PaginatedResult
    {
        // If filtering by specific channel, delegate to that channel
        if (isset($filters['channel']) && $filters['channel'] !== 'all') {
            $channelType = ChannelType::from($filters['channel']);
            $channel = $this->getChannel($channelType);

            if ($channel && $channel->isAvailable()) {
                return $channel->getConversations($filters, $perPage, $page);
            }
        }

        // Get from unified repository (aggregated view)
        return $this->repository->list($filters, $perPage, $page);
    }

    /**
     * Get a conversation by ID.
     */
    public function getConversation(int $id): ?UnifiedConversation
    {
        return $this->repository->findById($id);
    }

    /**
     * Get conversations linked to a CRM record across all channels.
     */
    public function getConversationsForRecord(RecordContext $context): array
    {
        // Get from unified repository
        $unified = $this->repository->getByRecordContext($context);

        // Optionally, also query each channel directly for real-time data
        // This can be controlled by a config option
        if (config('communication.realtime_record_lookup', false)) {
            foreach ($this->channels as $channel) {
                if ($channel->isAvailable()) {
                    $channelConversations = $channel->getConversationsForRecord($context);
                    // Merge, avoiding duplicates by source ID
                    // This logic would deduplicate based on sourceConversationId
                }
            }
        }

        return $unified;
    }

    /**
     * Get messages for a conversation.
     */
    public function getMessages(int $conversationId, int $limit = 50): array
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            return [];
        }

        // If we have a source conversation ID, try to get from the channel
        if ($conversation->getSourceConversationId()) {
            $channel = $this->getChannel($conversation->getChannel());

            if ($channel && $channel->isAvailable()) {
                return $channel->getMessages($conversation->getSourceConversationId(), $limit);
            }
        }

        // Fall back to unified repository
        return $this->repository->getMessages($conversationId, $limit);
    }

    /**
     * Send a message through the appropriate channel.
     */
    public function sendMessage(SendMessageDTO $dto): UnifiedMessage
    {
        $channel = $this->getChannel($dto->channel);

        if (!$channel) {
            throw new \InvalidArgumentException("Channel {$dto->channel->value} is not registered");
        }

        if (!$channel->isAvailable()) {
            throw new \RuntimeException("Channel {$dto->channel->value} is not available");
        }

        // Send through the channel
        $message = $channel->sendMessage($dto);

        // Save to unified repository for consistent querying
        return $this->repository->saveMessage($message);
    }

    /**
     * Reply to a conversation.
     */
    public function reply(int $conversationId, string $content, ?string $htmlContent = null, array $attachments = []): UnifiedMessage
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException("Conversation not found: {$conversationId}");
        }

        $channel = $this->getChannel($conversation->getChannel());

        if (!$channel || !$channel->isAvailable()) {
            throw new \RuntimeException("Channel {$conversation->getChannel()->value} is not available");
        }

        // Build the message DTO
        // The sender would be the current user (injected via auth context)
        // Recipients would be derived from the conversation contact
        $dto = new SendMessageDTO(
            channel: $conversation->getChannel(),
            conversationId: $conversation->getSourceConversationId() ?? (string) $conversationId,
            content: $content,
            htmlContent: $htmlContent,
            sender: $this->getCurrentUserParticipant(),
            recipients: [$conversation->getContact()],
            attachments: $attachments,
            recordContext: $conversation->getLinkedRecord(),
        );

        return $this->sendMessage($dto);
    }

    /**
     * Assign a conversation to a user.
     */
    public function assignConversation(int $conversationId, int $userId): UnifiedConversation
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException("Conversation not found: {$conversationId}");
        }

        $conversation->assign($userId);

        return $this->repository->save($conversation);
    }

    /**
     * Link a conversation to a CRM record.
     */
    public function linkToRecord(int $conversationId, RecordContext $context): UnifiedConversation
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException("Conversation not found: {$conversationId}");
        }

        $conversation->linkToRecord($context);

        return $this->repository->save($conversation);
    }

    /**
     * Update conversation status.
     */
    public function updateStatus(int $conversationId, string $status): UnifiedConversation
    {
        $conversation = $this->repository->findById($conversationId);

        if (!$conversation) {
            throw new \InvalidArgumentException("Conversation not found: {$conversationId}");
        }

        $conversation->updateStatus(\App\Domain\Communication\ValueObjects\ConversationStatus::from($status));

        return $this->repository->save($conversation);
    }

    /**
     * Get inbox statistics.
     */
    public function getStats(array $filters = []): array
    {
        return $this->repository->getStats($filters);
    }

    /**
     * Sync all channels.
     */
    public function syncAll(?int $userId = null): array
    {
        $results = [];

        foreach ($this->channels as $channelType => $channel) {
            if ($channel->isAvailable()) {
                try {
                    $count = $channel->sync($userId);
                    $results[$channelType] = ['success' => true, 'synced' => $count];
                } catch (\Exception $e) {
                    $results[$channelType] = ['success' => false, 'error' => $e->getMessage()];
                }
            }
        }

        return $results;
    }

    /**
     * Get the current user as a message participant.
     * This should be injected via AuthContext in a real implementation.
     */
    private function getCurrentUserParticipant(): \App\Domain\Communication\ValueObjects\MessageParticipant
    {
        // This would be replaced with actual auth context injection
        $user = auth()->user();

        return \App\Domain\Communication\ValueObjects\MessageParticipant::fromUser(
            userId: $user?->id ?? 0,
            name: $user?->name ?? 'System',
            email: $user?->email,
        );
    }
}
