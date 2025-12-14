<?php

declare(strict_types=1);

namespace App\Application\Services\Call;

use App\Domain\Call\Repositories\CallRepositoryInterface;

class CallApplicationService
{
    public function __construct(
        private CallRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
