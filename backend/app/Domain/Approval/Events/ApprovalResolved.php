<?php

declare(strict_types=1);

namespace App\Domain\Approval\Events;

use App\Domain\Approval\ValueObjects\ApprovalStatus;

final readonly class ApprovalResolved
{
    public function __construct(
        public int $requestId,
        public int $moduleId,
        public int $recordId,
        public ApprovalStatus $status,
        public ?int $resolvedBy = null,
    ) {}
}
