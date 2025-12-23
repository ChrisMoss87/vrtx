<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Shared\Contracts\EventDispatcherInterface;
use Illuminate\Support\Facades\Event;

/**
 * Laravel implementation of EventDispatcherInterface.
 */
final class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object|string $event, mixed $payload = null): mixed
    {
        return Event::dispatch($event, $payload);
    }

    public function dispatchIf(bool $condition, object|string $event, mixed $payload = null): mixed
    {
        if ($condition) {
            return $this->dispatch($event, $payload);
        }

        return null;
    }

    public function dispatchUnless(bool $condition, object|string $event, mixed $payload = null): mixed
    {
        if (!$condition) {
            return $this->dispatch($event, $payload);
        }

        return null;
    }
}
