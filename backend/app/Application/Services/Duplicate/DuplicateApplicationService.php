<?php

declare(strict_types=1);

namespace App\Application\Services\Duplicate;

use App\Domain\Duplicate\Repositories\DuplicateCandidateRepositoryInterface;

class DuplicateApplicationService
{
    public function __construct(
        private DuplicateCandidateRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
