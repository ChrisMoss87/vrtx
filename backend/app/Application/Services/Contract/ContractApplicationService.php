<?php

declare(strict_types=1);

namespace App\Application\Services\Contract;

use App\Domain\Contract\Repositories\ContractRepositoryInterface;

class ContractApplicationService
{
    public function __construct(
        private ContractRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
