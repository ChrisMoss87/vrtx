<?php

declare(strict_types=1);

namespace App\Application\Services\ImportExport;

use App\Domain\ImportExport\Repositories\ImportRepositoryInterface;

class ImportExportApplicationService
{
    public function __construct(
        private ImportRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
