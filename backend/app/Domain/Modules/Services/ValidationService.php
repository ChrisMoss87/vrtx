<?php

declare(strict_types=1);

namespace App\Domain\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Shared\Contracts\ValidatorInterface;

class ValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * Validate record data against module field definitions.
     */
    public function validateRecordData(Module $module, array $data): void
    {
        $rules = [];
        $messages = [];

        foreach ($module->getFields() as $field) {
            $fieldRules = [];

            // Required validation
            if ($field->isRequired()) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Type-specific validation
            $fieldRules = array_merge($fieldRules, $this->getTypeValidation($field->type()));

            // Unique validation
            if ($field->isUnique()) {
                $fieldRules[] = 'unique:module_records,data->' . $field->apiName();
            }

            // Custom validation rules from field definition
            $customRules = $field->validationRules()->rules();
            if (!empty($customRules)) {
                $fieldRules = array_merge($fieldRules, $customRules);
            }

            $rules[$field->apiName()] = $fieldRules;

            // Custom error messages
            if ($field->helpText()) {
                $messages[$field->apiName() . '.required'] = $field->helpText();
            }
        }

        $result = $this->validator->validate($data, $rules, $messages);

        if ($result->fails()) {
            throw new \InvalidArgumentException(
                'Validation failed: ' . json_encode($result->errors())
            );
        }
    }

    /**
     * Get validation rules based on field type.
     */
    private function getTypeValidation(string $type): array
    {
        return match ($type) {
            'text', 'textarea' => ['string'],
            'email' => ['email'],
            'url' => ['url'],
            'phone' => ['string'],
            'number', 'decimal' => ['numeric'],
            'currency' => ['numeric', 'min:0'],
            'percent' => ['numeric', 'min:0', 'max:100'],
            'date' => ['date'],
            'datetime' => ['date'],
            'time' => ['date_format:H:i'],
            'checkbox', 'toggle' => ['boolean'],
            'select', 'radio' => ['string'],
            'multiselect' => ['array'],
            'file', 'image' => ['string'], // File paths or URLs
            'rich_text' => ['string'],
            'lookup' => ['integer', 'exists:module_records,id'],
            default => ['string'],
        };
    }
}
