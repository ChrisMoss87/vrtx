<?php

declare(strict_types=1);

namespace App\Application\Services\Webhook;

use App\Domain\Webhook\Repositories\WebhookRepositoryInterface;

class WebhookApplicationService
{
    public function __construct(
        private WebhookRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
