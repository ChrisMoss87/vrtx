<?php

declare(strict_types=1);

namespace App\Domain\Approval\Events;

final readonly class ApprovalRequested
{
    public function __construct(
        public int $requestId,
        public int $moduleId,
        public int $recordId,
        public array $approverIds,
        public ?int $requestedBy = null,
    ) {}
}
