<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

final readonly class ModuleSettings implements JsonSerializable
{
    public function __construct(
        public bool $hasImport,
        public bool $hasExport,
        public bool $hasMassActions,
        public bool $hasComments,
        public bool $hasAttachments,
        public bool $hasActivityLog,
        public bool $hasCustomViews,
        public ?string $recordNameField,
        public array $additionalSettings,
    ) {}

    public static function default(): self
    {
        return new self(
            hasImport: true,
            hasExport: true,
            hasMassActions: true,
            hasComments: true,
            hasAttachments: true,
            hasActivityLog: true,
            hasCustomViews: true,
            recordNameField: null,
            additionalSettings: [],
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            hasImport: $data['has_import'] ?? true,
            hasExport: $data['has_export'] ?? true,
            hasMassActions: $data['has_mass_actions'] ?? true,
            hasComments: $data['has_comments'] ?? true,
            hasAttachments: $data['has_attachments'] ?? true,
            hasActivityLog: $data['has_activity_log'] ?? true,
            hasCustomViews: $data['has_custom_views'] ?? true,
            recordNameField: $data['record_name_field'] ?? null,
            additionalSettings: $data['additional_settings'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'has_import' => $this->hasImport,
            'has_export' => $this->hasExport,
            'has_mass_actions' => $this->hasMassActions,
            'has_comments' => $this->hasComments,
            'has_attachments' => $this->hasAttachments,
            'has_activity_log' => $this->hasActivityLog,
            'has_custom_views' => $this->hasCustomViews,
            'record_name_field' => $this->recordNameField,
            'additional_settings' => $this->additionalSettings,
        ];
    }
}
