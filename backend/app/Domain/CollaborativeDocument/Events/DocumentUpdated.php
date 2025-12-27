<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\Events;

use App\Domain\CollaborativeDocument\Entities\CollaborativeDocument;
use App\Domain\Shared\Events\DomainEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DocumentUpdated extends DomainEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private readonly int $documentId,
        private readonly int $userId,
        private readonly string $yjsUpdate,
    ) {
        parent::__construct();
    }

    public function getDocumentId(): int
    {
        return $this->documentId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getYjsUpdate(): string
    {
        return $this->yjsUpdate;
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
            'user_id' => $this->userId,
            'yjs_update' => $this->yjsUpdate,
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
        return 'document.updated';
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
            'user_id' => $this->userId,
            'yjs_update' => $this->yjsUpdate,
        ];
    }
}
