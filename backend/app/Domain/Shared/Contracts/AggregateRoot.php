<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Base interface for aggregate roots.
 *
 * An Aggregate Root is the entry point to an aggregate - a cluster of domain objects
 * that can be treated as a single unit. The aggregate root guarantees the consistency
 * of changes being made within the aggregate.
 */
interface AggregateRoot extends Entity
{
    /**
     * Get all domain events that have been raised by this aggregate.
     *
     * @return array<DomainEvent>
     */
    public function pullDomainEvents(): array;

    /**
     * Record a domain event to be dispatched later.
     */
    public function recordEvent(DomainEvent $event): void;

    /**
     * Clear all recorded domain events.
     */
    public function clearDomainEvents(): void;
}
