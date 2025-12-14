<?php

declare(strict_types=1);

namespace App\Application\Services\Support;

use App\Domain\Support\Repositories\SupportTicketRepositoryInterface;

class SupportApplicationService
{
    public function __construct(
        private SupportTicketRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
