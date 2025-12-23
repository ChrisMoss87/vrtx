<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Shared\Contracts\ValidatorInterface;
use App\Domain\Shared\Contracts\ValidationResult;
use Illuminate\Support\Facades\Validator;

/**
 * Laravel implementation of ValidatorInterface.
 */
final class LaravelValidator implements ValidatorInterface
{
    public function validate(
        array $data,
        array $rules,
        array $messages = [],
        array $attributes = []
    ): ValidationResult {
        $validator = Validator::make($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return new ValidationResult(
                passes: false,
                errors: $validator->errors()->toArray(),
                validated: [],
            );
        }

        return new ValidationResult(
            passes: true,
            errors: [],
            validated: $validator->validated(),
        );
    }

    public function passes(array $data, array $rules): bool
    {
        return Validator::make($data, $rules)->passes();
    }

    public function fails(array $data, array $rules): bool
    {
        return Validator::make($data, $rules)->fails();
    }
}
