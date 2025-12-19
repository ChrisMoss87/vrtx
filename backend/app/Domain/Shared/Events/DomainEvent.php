<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

use DateTimeImmutable;

/**
 * Base class for all domain events.
 *
 * Domain events represent something that happened in the domain that domain experts
 * care about. They are immutable and carry all the data needed to describe the event.
 */
abstract class DomainEvent
{
    private readonly DateTimeImmutable $occurredAt;
    private readonly string $eventId;

    public function __construct()
    {
        $this->occurredAt = new DateTimeImmutable();
        $this->eventId = $this->generateEventId();
    }

    /**
     * Get the time when this event occurred.
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Get the unique identifier for this event instance.
     */
    public function eventId(): string
    {
        return $this->eventId;
    }

    /**
     * Get the name of the event for serialization/logging.
     */
    public function eventName(): string
    {
        return static::class;
    }

    /**
     * Get the aggregate root ID this event relates to.
     */
    abstract public function aggregateId(): int|string;

    /**
     * Get the aggregate root type this event relates to.
     */
    abstract public function aggregateType(): string;

    /**
     * Convert event payload to array for serialization.
     *
     * @return array<string, mixed>
     */
    abstract public function toPayload(): array;

    /**
     * Generate a unique event ID.
     */
    private function generateEventId(): string
    {
        return sprintf(
            '%s-%s-%s',
            (new DateTimeImmutable())->format('YmdHis'),
            bin2hex(random_bytes(4)),
            substr(md5(static::class), 0, 8)
        );
    }
}
