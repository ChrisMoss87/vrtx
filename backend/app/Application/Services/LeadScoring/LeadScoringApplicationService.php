<?php

declare(strict_types=1);

namespace App\Application\Services\LeadScoring;

use App\Domain\LeadScoring\Repositories\ScoringModelRepositoryInterface;

class LeadScoringApplicationService
{
    public function __construct(
        private ScoringModelRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
