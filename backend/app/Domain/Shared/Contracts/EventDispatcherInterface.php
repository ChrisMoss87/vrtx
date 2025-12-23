<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Domain interface for event dispatching.
 *
 * This abstracts the event dispatching mechanism from the domain layer,
 * allowing the domain to dispatch events without depending on Laravel.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event.
     *
     * @param object|string $event The event object or event name
     * @param mixed $payload Optional payload for string events
     * @return mixed
     */
    public function dispatch(object|string $event, mixed $payload = null): mixed;

    /**
     * Dispatch an event if a condition is true.
     *
     * @param bool $condition
     * @param object|string $event
     * @param mixed $payload
     * @return mixed
     */
    public function dispatchIf(bool $condition, object|string $event, mixed $payload = null): mixed;

    /**
     * Dispatch an event unless a condition is true.
     *
     * @param bool $condition
     * @param object|string $event
     * @param mixed $payload
     * @return mixed
     */
    public function dispatchUnless(bool $condition, object|string $event, mixed $payload = null): mixed;
}
