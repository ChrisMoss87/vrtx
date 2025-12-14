<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Events;

/**
 * Event fired when an approval is requested for a transition.
 */
final readonly class ApprovalRequested
{
    public function __construct(
        public int $blueprintId,
        public int $transitionId,
        public int $recordId,
        public int $approvalRequestId,
        public array $approverIds,
        public ?int $requestedByUserId = null,
    ) {}
}
