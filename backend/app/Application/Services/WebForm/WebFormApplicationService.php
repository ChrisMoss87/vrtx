<?php

declare(strict_types=1);

namespace App\Application\Services\WebForm;

use App\Domain\WebForm\Repositories\WebFormRepositoryInterface;

class WebFormApplicationService
{
    public function __construct(
        private WebFormRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
