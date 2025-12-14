<?php

declare(strict_types=1);

namespace App\Application\Services\Chat;

use App\Domain\Chat\Repositories\ChatConversationRepositoryInterface;

class ChatApplicationService
{
    public function __construct(
        private ChatConversationRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
