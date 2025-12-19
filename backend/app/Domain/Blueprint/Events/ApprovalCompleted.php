<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Events;

use App\Domain\Blueprint\ValueObjects\ApprovalStatus;

/**
 * Event fired when an approval is completed.
 */
final readonly class ApprovalCompleted
{
    public function __construct(
        public int $blueprintId,
        public int $transitionId,
        public int $recordId,
        public int $approvalRequestId,
        public ApprovalStatus $status,
        public ?int $approvedByUserId = null,
        public ?string $comment = null,
    ) {}
}
