<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Domain interface for validation.
 *
 * This abstracts the validation mechanism from the domain layer,
 * allowing domain services to validate data without depending on Laravel.
 */
interface ValidatorInterface
{
    /**
     * Validate data against rules.
     *
     * @param array $data The data to validate
     * @param array $rules The validation rules
     * @param array $messages Custom error messages
     * @param array $attributes Custom attribute names
     * @return ValidationResult
     */
    public function validate(
        array $data,
        array $rules,
        array $messages = [],
        array $attributes = []
    ): ValidationResult;

    /**
     * Check if data passes validation.
     *
     * @param array $data The data to validate
     * @param array $rules The validation rules
     * @return bool
     */
    public function passes(array $data, array $rules): bool;

    /**
     * Check if data fails validation.
     *
     * @param array $data The data to validate
     * @param array $rules The validation rules
     * @return bool
     */
    public function fails(array $data, array $rules): bool;
}
