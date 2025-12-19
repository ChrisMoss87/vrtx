<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Events;

/**
 * Event fired when a blueprint transition fails.
 */
final readonly class TransitionFailed
{
    public function __construct(
        public int $blueprintId,
        public int $transitionId,
        public int $recordId,
        public string $errorMessage,
        public ?int $executedByUserId = null,
    ) {}
}
