<?php

declare(strict_types=1);

namespace App\Domain\Modules\Entities;

use App\Domain\Modules\ValueObjects\FieldSettings;
use App\Domain\Modules\ValueObjects\FieldType;
use App\Domain\Modules\ValueObjects\ValidationRules;
use DateTimeImmutable;
use Illuminate\Support\Str;

final class Field
{
    private array $options = [];

    public function __construct(
        private ?int $id,
        private int $moduleId,
        private ?int $blockId,
        private string $label,
        private string $apiName,
        private FieldType $type,
        private ?string $description,
        private ?string $helpText,
        private bool $isRequired,
        private bool $isUnique,
        private bool $isSearchable,
        private bool $isFilterable,
        private bool $isSortable,
        private ValidationRules $validationRules,
        private FieldSettings $settings,
        private ?string $defaultValue,
        private int $displayOrder,
        private int $width,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function create(
        int $moduleId,
        ?int $blockId,
        string $label,
        FieldType $type,
        ?string $description = null,
        ?string $helpText = null,
        bool $isRequired = false,
        bool $isUnique = false,
        bool $isSearchable = true,
        bool $isFilterable = true,
        bool $isSortable = true,
        ?ValidationRules $validationRules = null,
        ?FieldSettings $settings = null,
        ?string $defaultValue = null,
        int $displayOrder = 0,
        int $width = 100
    ): self {
        $apiName = Str::snake($label);

        return new self(
            id: null,
            moduleId: $moduleId,
            blockId: $blockId,
            label: $label,
            apiName: $apiName,
            type: $type,
            description: $description,
            helpText: $helpText,
            isRequired: $isRequired,
            isUnique: $isUnique,
            isSearchable: $isSearchable,
            isFilterable: $isFilterable,
            isSortable: $isSortable,
            validationRules: $validationRules ?? ValidationRules::empty(),
            settings: $settings ?? FieldSettings::default(),
            defaultValue: $defaultValue,
            displayOrder: $displayOrder,
            width: $width,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function addOption(FieldOption $option): void
    {
        $this->options[] = $option;
    }

    public function updateDetails(
        string $label,
        FieldType $type,
        ?string $description,
        ?string $helpText
    ): void {
        $this->label = $label;
        $this->apiName = Str::snake($label);
        $this->type = $type;
        $this->description = $description;
        $this->helpText = $helpText;
    }

    public function updateValidation(
        bool $isRequired,
        bool $isUnique,
        bool $isSearchable,
        bool $isFilterable,
        bool $isSortable,
        ValidationRules $rules
    ): void {
        $this->isRequired = $isRequired;
        $this->isUnique = $isUnique;
        $this->isSearchable = $isSearchable;
        $this->isFilterable = $isFilterable;
        $this->isSortable = $isSortable;
        $this->validationRules = $rules;
    }

    public function updateSettings(FieldSettings $settings): void
    {
        $this->settings = $settings;
    }

    public function updateLayout(int $displayOrder, int $width): void
    {
        $this->displayOrder = $displayOrder;
        $this->width = $width;
    }

    // Getters
    public function id(): ?int
    {
        return $this->id;
    }

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function blockId(): ?int
    {
        return $this->blockId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function apiName(): string
    {
        return $this->apiName;
    }

    public function type(): FieldType
    {
        return $this->type;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function helpText(): ?string
    {
        return $this->helpText;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function isUnique(): bool
    {
        return $this->isUnique;
    }

    public function isSearchable(): bool
    {
        return $this->isSearchable;
    }

    public function isFilterable(): bool
    {
        return $this->isFilterable;
    }

    public function isSortable(): bool
    {
        return $this->isSortable;
    }

    public function validationRules(): ValidationRules
    {
        return $this->validationRules;
    }

    public function settings(): FieldSettings
    {
        return $this->settings;
    }

    public function defaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
