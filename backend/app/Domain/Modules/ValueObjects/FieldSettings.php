<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

final readonly class FieldSettings implements JsonSerializable
{
    public function __construct(
        public ?int $minLength,
        public ?int $maxLength,
        public ?float $minValue,
        public ?float $maxValue,
        public ?string $pattern,
        public ?int $precision, // For decimal/currency fields
        public ?string $currencyCode, // For currency fields
        public ?int $relatedModuleId, // For lookup fields
        public ?string $formula, // For formula fields
        public ?array $allowedFileTypes, // For file/image fields
        public ?int $maxFileSize, // In KB
        public array $additionalSettings,
    ) {}

    public static function default(): self
    {
        return new self(
            minLength: null,
            maxLength: null,
            minValue: null,
            maxValue: null,
            pattern: null,
            precision: 2,
            currencyCode: 'USD',
            relatedModuleId: null,
            formula: null,
            allowedFileTypes: null,
            maxFileSize: 5120, // 5MB default
            additionalSettings: [],
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            minLength: $data['min_length'] ?? null,
            maxLength: $data['max_length'] ?? null,
            minValue: isset($data['min_value']) ? (float) $data['min_value'] : null,
            maxValue: isset($data['max_value']) ? (float) $data['max_value'] : null,
            pattern: $data['pattern'] ?? null,
            precision: $data['precision'] ?? null,
            currencyCode: $data['currency_code'] ?? null,
            relatedModuleId: $data['related_module_id'] ?? null,
            formula: $data['formula'] ?? null,
            allowedFileTypes: $data['allowed_file_types'] ?? null,
            maxFileSize: $data['max_file_size'] ?? null,
            additionalSettings: $data['additional_settings'] ?? [],
        );
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            'min_length' => $this->minLength,
            'max_length' => $this->maxLength,
            'min_value' => $this->minValue,
            'max_value' => $this->maxValue,
            'pattern' => $this->pattern,
            'precision' => $this->precision,
            'currency_code' => $this->currencyCode,
            'related_module_id' => $this->relatedModuleId,
            'formula' => $this->formula,
            'allowed_file_types' => $this->allowedFileTypes,
            'max_file_size' => $this->maxFileSize,
            'additional_settings' => $this->additionalSettings,
        ], static fn ($value): bool => $value !== null);
    }
}
