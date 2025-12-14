<?php

declare(strict_types=1);

namespace App\Application\Services\LandingPage;

use App\Domain\LandingPage\Repositories\LandingPageRepositoryInterface;

class LandingPageApplicationService
{
    public function __construct(
        private LandingPageRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
