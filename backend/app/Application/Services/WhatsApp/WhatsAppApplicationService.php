<?php

declare(strict_types=1);

namespace App\Application\Services\WhatsApp;

use App\Domain\WhatsApp\Repositories\WhatsappConversationRepositoryInterface;

class WhatsAppApplicationService
{
    public function __construct(
        private WhatsappConversationRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
