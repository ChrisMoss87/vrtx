<?php

declare(strict_types=1);

namespace App\Application\Services\Playbook;

use App\Domain\Playbook\Repositories\PlaybookRepositoryInterface;

class PlaybookApplicationService
{
    public function __construct(
        private PlaybookRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
