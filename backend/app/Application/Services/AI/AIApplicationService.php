<?php

declare(strict_types=1);

namespace App\Application\Services\AI;

use App\Domain\AI\Repositories\AiPromptRepositoryInterface;

class AIApplicationService
{
    public function __construct(
        private AiPromptRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
