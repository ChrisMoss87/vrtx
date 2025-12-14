<?php

declare(strict_types=1);

namespace App\Application\Services\Activity;

use App\Domain\Activity\Repositories\ActivityRepositoryInterface;

class ActivityApplicationService
{
    public function __construct(
        private ActivityRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
