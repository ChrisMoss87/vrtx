<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public object $notification
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.' . $this->notification->user_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'category' => $this->notification->category ?? null,
            'title' => $this->notification->title,
            'body' => $this->notification->body ?? null,
            'icon' => $this->notification->icon ?? null,
            'icon_color' => $this->notification->icon_color ?? null,
            'action_url' => $this->notification->action_url ?? null,
            'action_label' => $this->notification->action_label ?? null,
            'data' => is_string($this->notification->data ?? null)
                ? json_decode($this->notification->data, true)
                : ($this->notification->data ?? null),
            'read_at' => $this->notification->read_at ?? null,
            'created_at' => $this->notification->created_at,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }
}
