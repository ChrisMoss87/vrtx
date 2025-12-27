<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Events;

use App\Domain\CollaborativeDocument\Entities\CollaborativeDocument;
use App\Domain\CollaborativeDocument\Entities\DocumentComment;
use App\Domain\Shared\Events\DomainEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CommentAdded extends DomainEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private readonly int $documentId,
        private readonly int $commentId,
        private readonly int $userId,
        private readonly bool $isReply = false,
    ) {
        parent::__construct();
    }

    public static function fromComment(DocumentComment $comment): self
    {
        return new self(
            documentId: $comment->getDocumentId(),
            commentId: $comment->getId(),
            userId: $comment->getUserId(),
            isReply: $comment->getThreadId() !== null,
        );
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function getCommentId(): int
    {
        return $this->commentId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function isReply(): bool
    {
        return $this->isReply;
    }

    public function aggregateId(): int|string
    {
        return $this->documentId;
    }

    public function aggregateType(): string
    {
        return CollaborativeDocument::class;
    }

    public function toPayload(): array
    {
        return [
            'document_id' => $this->documentId,
            'comment_id' => $this->commentId,
            'user_id' => $this->userId,
            'is_reply' => $this->isReply,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('document.' . $this->documentId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'comment.added';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->documentId,
            'comment_id' => $this->commentId,
            'user_id' => $this->userId,
            'is_reply' => $this->isReply,
        ];
    }
}
