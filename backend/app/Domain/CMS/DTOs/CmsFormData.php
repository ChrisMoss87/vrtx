<?php

declare(strict_types=1);

namespace App\Domain\CMS\DTOs;

final readonly class CmsFormData
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description = null,
        public array $fields = [],
        public ?array $settings = null,
        public string $submitAction = 'create_lead',
        public ?int $targetModuleId = null,
        public ?array $fieldMapping = null,
        public string $submitButtonText = 'Submit',
        public ?string $successMessage = null,
        public ?string $redirectUrl = null,
        public ?array $notificationEmails = null,
        public ?int $notificationTemplateId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            slug: $data['slug'],
            description: $data['description'] ?? null,
            fields: $data['fields'] ?? [],
            settings: $data['settings'] ?? null,
            submitAction: $data['submit_action'] ?? 'create_lead',
            targetModuleId: $data['target_module_id'] ?? null,
            fieldMapping: $data['field_mapping'] ?? null,
            submitButtonText: $data['submit_button_text'] ?? 'Submit',
            successMessage: $data['success_message'] ?? null,
            redirectUrl: $data['redirect_url'] ?? null,
            notificationEmails: $data['notification_emails'] ?? null,
            notificationTemplateId: $data['notification_template_id'] ?? null,
        );
    }
}
