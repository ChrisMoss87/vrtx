<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Events;

/**
 * Event fired when an SLA enters warning state.
 */
final readonly class SlaWarning
{
    public function __construct(
        public int $blueprintId,
        public int $slaId,
        public int $recordId,
        public int $stateId,
        public int $hoursRemaining,
    ) {}
}
