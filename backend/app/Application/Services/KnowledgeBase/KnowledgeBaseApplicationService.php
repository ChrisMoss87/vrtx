<?php

declare(strict_types=1);

namespace App\Application\Services\KnowledgeBase;

use App\Domain\KnowledgeBase\Repositories\KbArticleRepositoryInterface;

class KnowledgeBaseApplicationService
{
    public function __construct(
        private KbArticleRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
