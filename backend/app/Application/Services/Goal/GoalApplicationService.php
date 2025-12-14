<?php

declare(strict_types=1);

namespace App\Application\Services\Goal;

use App\Domain\Goal\Repositories\GoalRepositoryInterface;

class GoalApplicationService
{
    public function __construct(
        private GoalRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
