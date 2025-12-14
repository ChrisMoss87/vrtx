<?php

declare(strict_types=1);

namespace App\Application\Services\Pipeline;

use App\Domain\Pipeline\Repositories\PipelineRepositoryInterface;

class PipelineApplicationService
{
    public function __construct(
        private PipelineRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
