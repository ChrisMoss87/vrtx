<?php

declare(strict_types=1);

namespace App\Domain\Email\DTOs;

use App\Domain\Email\Entities\EmailMessage;

final readonly class EmailResponseDTO
{
    public function __construct(
        public int $id,
        public int $accountId,
        public string $fromEmail,
        public ?string $fromName,
        public array $toRecipients,
        public array $ccRecipients,
        public array $bccRecipients,
        public string $subject,
        public ?string $bodyHtml,
        public ?string $bodyText,
        public string $type,
        public string $status,
        public string $statusLabel,
        public array $attachments,
        public ?int $recordId,
        public ?int $moduleId,
        public ?string $sentAt,
        public ?string $openedAt,
        public int $openCount,
        public int $clickCount,
        public string $createdAt,
    ) {}

    public static function fromEntity(EmailMessage $email): self
    {
        return new self(
            id: $email->getId(),
            accountId: $email->getAccountId(),
            fromEmail: $email->getFromEmail(),
            fromName: $email->getFromName(),
            toRecipients: $email->getToRecipients(),
            ccRecipients: $email->getCcRecipients(),
            bccRecipients: $email->getBccRecipients(),
            subject: $email->getSubject(),
            bodyHtml: $email->getBodyHtml(),
            bodyText: $email->getBodyText(),
            type: $email->getType()->value,
            status: $email->getStatus()->value,
            statusLabel: $email->getStatus()->label(),
            attachments: $email->getAttachments(),
            recordId: $email->getRecordId(),
            moduleId: $email->getModuleId(),
            sentAt: $email->getSentAt()?->format('c'),
            openedAt: null,
            openCount: $email->getOpenCount(),
            clickCount: $email->getClickCount(),
            createdAt: '',
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
