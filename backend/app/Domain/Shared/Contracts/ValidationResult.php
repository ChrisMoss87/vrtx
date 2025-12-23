<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Value object representing a validation result.
 */
readonly class ValidationResult
{
    /**
     * @param bool $passes Whether validation passed
     * @param array<string, array<string>> $errors Validation errors by field
     * @param array $validated The validated data
     */
    public function __construct(
        public bool $passes,
        public array $errors = [],
        public array $validated = [],
    ) {}

    /**
     * Check if validation passed.
     */
    public function passes(): bool
    {
        return $this->passes;
    }

    /**
     * Check if validation failed.
     */
    public function fails(): bool
    {
        return !$this->passes;
    }

    /**
     * Get all errors.
     *
     * @return array<string, array<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error for a field.
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all errors for a field.
     *
     * @return array<string>
     */
    public function get(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a field has errors.
     */
    public function has(string $field): bool
    {
        return isset($this->errors[$field]) && count($this->errors[$field]) > 0;
    }

    /**
     * Get the validated data.
     */
    public function validated(): array
    {
        return $this->validated;
    }
}
