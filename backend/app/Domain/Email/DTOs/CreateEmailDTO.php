<?php

declare(strict_types=1);

namespace App\Domain\Email\DTOs;

final readonly class CreateEmailDTO
{
    public function __construct(
        public int $accountId,
        public string $fromEmail,
        public array $toRecipients,
        public string $subject,
        public ?string $bodyHtml = null,
        public ?string $bodyText = null,
        public array $ccRecipients = [],
        public array $bccRecipients = [],
        public array $attachments = [],
        public ?int $moduleId = null,
        public ?int $recordId = null,
        public ?int $templateId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            accountId: (int) $data['account_id'],
            fromEmail: $data['from_email'],
            toRecipients: $data['to'] ?? $data['to_recipients'] ?? [],
            subject: $data['subject'],
            bodyHtml: $data['body_html'] ?? $data['body'] ?? null,
            bodyText: $data['body_text'] ?? null,
            ccRecipients: $data['cc'] ?? $data['cc_recipients'] ?? [],
            bccRecipients: $data['bcc'] ?? $data['bcc_recipients'] ?? [],
            attachments: $data['attachments'] ?? [],
            moduleId: isset($data['module_id']) ? (int) $data['module_id'] : null,
            recordId: isset($data['record_id']) ? (int) $data['record_id'] : null,
            templateId: isset($data['template_id']) ? (int) $data['template_id'] : null,
        );
    }
}
