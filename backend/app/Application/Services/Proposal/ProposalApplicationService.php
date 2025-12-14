<?php

declare(strict_types=1);

namespace App\Application\Services\Proposal;

use App\Domain\Proposal\Repositories\ProposalRepositoryInterface;

class ProposalApplicationService
{
    public function __construct(
        private ProposalRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
