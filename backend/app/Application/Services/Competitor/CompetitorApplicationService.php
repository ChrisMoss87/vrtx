<?php

declare(strict_types=1);

namespace App\Application\Services\Competitor;

use App\Domain\Competitor\Repositories\CompetitorRepositoryInterface;

class CompetitorApplicationService
{
    public function __construct(
        private CompetitorRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
