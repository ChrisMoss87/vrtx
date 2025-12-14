<?php

declare(strict_types=1);

namespace App\Domain\Email\Events;

final readonly class EmailBounced
{
    public function __construct(
        public int $emailId,
        public string $bounceType,
        public string $reason,
        public string $recipient,
    ) {}
}
