<?php

declare(strict_types=1);

namespace App\Domain\Email\Events;

final readonly class EmailOpened
{
    public function __construct(
        public int $emailId,
        public int $openCount,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
