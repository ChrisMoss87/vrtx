<?php

declare(strict_types=1);

namespace App\Application\Services\Cadence;

use App\Domain\Cadence\Repositories\CadenceRepositoryInterface;

class CadenceApplicationService
{
    public function __construct(
        private CadenceRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
