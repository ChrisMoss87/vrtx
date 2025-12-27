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

final class CollaboratorJoined extends DomainEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        private readonly int $documentId,
        private readonly int $userId,
        private readonly string $userName,
        private readonly string $userColor,
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

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getUserColor(): string
    {
        return $this->userColor;
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
            'user_name' => $this->userName,
            'user_color' => $this->userColor,
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
        return 'collaborator.joined';
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
            'user_name' => $this->userName,
            'user_color' => $this->userColor,
        ];
    }
}
