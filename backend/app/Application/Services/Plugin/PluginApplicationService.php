<?php

declare(strict_types=1);

namespace App\Application\Services\Plugin;

use App\Domain\Plugin\Repositories\PluginRepositoryInterface;

class PluginApplicationService
{
    public function __construct(
        private PluginRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
