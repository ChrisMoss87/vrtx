<?php

declare(strict_types=1);

namespace App\Application\Services\Inbox;

use App\Domain\Inbox\Repositories\InboxConversationRepositoryInterface;

class InboxApplicationService
{
    public function __construct(
        private InboxConversationRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
