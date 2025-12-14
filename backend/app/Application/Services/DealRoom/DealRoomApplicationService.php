<?php

declare(strict_types=1);

namespace App\Application\Services\DealRoom;

use App\Domain\DealRoom\Repositories\DealRoomRepositoryInterface;

class DealRoomApplicationService
{
    public function __construct(
        private DealRoomRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
