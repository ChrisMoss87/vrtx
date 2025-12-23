<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners;

use App\Domain\Shared\Contracts\LoggerInterface;
use App\Domain\Shared\Events\DomainEvent;
use Throwable;

/**
 * Base class for domain event listeners.
 *
 * Provides common functionality for all domain event listeners including
 * logging, error handling, and event metadata access.
 */
abstract class DomainEventListener
{
    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * Handle the domain event.
     *
     * This method wraps the actual handling logic with error handling
     * and logging. Subclasses should implement doHandle() instead.
     */
    public function handle(DomainEvent $event): void
    {
        $listenerName = static::class;
        $eventName = $event->eventName();

        try {
            $this->logger->debug('Handling domain event', [
                'listener' => $listenerName,
                'event' => $eventName,
                'event_id' => $event->eventId(),
                'aggregate_type' => $event->aggregateType(),
                'aggregate_id' => $event->aggregateId(),
            ]);

            $this->doHandle($event);

            $this->logger->debug('Domain event handled successfully', [
                'listener' => $listenerName,
                'event' => $eventName,
                'event_id' => $event->eventId(),
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to handle domain event', [
                'listener' => $listenerName,
                'event' => $eventName,
                'event_id' => $event->eventId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw if the listener should fail loudly
            if ($this->shouldFailLoudly()) {
                throw $e;
            }
        }
    }

    /**
     * Actually handle the domain event.
     *
     * Implement this method in subclasses to define the event handling logic.
     */
    abstract protected function doHandle(DomainEvent $event): void;

    /**
     * Determine if the listener should re-throw exceptions.
     *
     * Override this method to return true if failures should propagate.
     * By default, errors are logged but don't break the flow.
     */
    protected function shouldFailLoudly(): bool
    {
        return false;
    }
}
