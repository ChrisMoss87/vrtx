<?php

declare(strict_types=1);

namespace App\Domain\Email\Events;

final readonly class EmailSent
{
    public function __construct(
        public int $emailId,
        public int $accountId,
        public string $messageId,
        public array $toRecipients,
        public ?int $recordId = null,
        public ?int $moduleId = null,
    ) {}
}
