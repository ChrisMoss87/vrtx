<?php

declare(strict_types=1);

namespace App\Application\Services\Sms;

use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;

class SmsApplicationService
{
    public function __construct(
        private SmsMessageRepositoryInterface $repository,
    ) {}

    // TODO: Add use case methods
}
