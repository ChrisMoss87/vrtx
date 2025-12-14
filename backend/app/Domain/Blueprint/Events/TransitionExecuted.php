<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Events;

/**
 * Event fired when a blueprint transition is executed.
 */
final readonly class TransitionExecuted
{
    public function __construct(
        public int $blueprintId,
        public int $transitionId,
        public int $recordId,
        public int $fromStateId,
        public int $toStateId,
        public ?int $executedByUserId = null,
    ) {}
}
