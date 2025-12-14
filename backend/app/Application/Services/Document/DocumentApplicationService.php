<?php

declare(strict_types=1);

namespace App\Application\Services\Document;

use App\Domain\Document\Repositories\SignatureRequestRepositoryInterface;

class DocumentApplicationService
{
    public function __construct(
        private SignatureRequestRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
