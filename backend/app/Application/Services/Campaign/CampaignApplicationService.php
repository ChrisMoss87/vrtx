<?php

declare(strict_types=1);

namespace App\Application\Services\Campaign;

use App\Domain\Campaign\Repositories\CampaignRepositoryInterface;

class CampaignApplicationService
{
    public function __construct(
        private CampaignRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
