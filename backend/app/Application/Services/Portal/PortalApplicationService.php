<?php

declare(strict_types=1);

namespace App\Application\Services\Portal;

use App\Domain\Portal\Repositories\PortalUserRepositoryInterface;

class PortalApplicationService
{
    public function __construct(
        private PortalUserRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
