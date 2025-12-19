<?php

declare(strict_types=1);

namespace App\Domain\Shared\Traits;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Trait that provides domain event recording functionality.
 *
 * Use this trait in aggregate roots to enable recording and dispatching
 * of domain events.
 */
trait HasDomainEvents
{
    /** @var array<DomainEvent> */
    private array $domainEvents = [];

    /**
     * Get all domain events that have been raised by this aggregate.
     *
     * @return array<DomainEvent>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    /**
     * Record a domain event to be dispatched later.
     */
    public function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Clear all recorded domain events.
     */
    public function clearDomainEvents(): void
    {
        $this->domainEvents = [];
    }

    /**
     * Check if there are any pending domain events.
     */
    public function hasDomainEvents(): bool
    {
        return count($this->domainEvents) > 0;
    }
}
