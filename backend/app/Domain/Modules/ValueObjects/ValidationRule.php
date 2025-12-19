<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

/**
 * Represents validation rules for a field.
 *
 * Encapsulates Laravel validation rules, custom messages, and custom validation logic.
 */
final readonly class ValidationRule implements JsonSerializable
{
    /**
     * @param array<string> $rules Laravel validation rules
     * @param array<string, string> $messages Custom validation error messages
     * @param array<string, mixed> $customValidation Custom validation configuration
     */
    public function __construct(
        public array $rules,
        public array $messages = [],
        public array $customValidation = [],
    ) {}

    /**
     * Create an empty ValidationRule (no validation).
     */
    public static function none(): self
    {
        return new self(
            rules: [],
            messages: [],
            customValidation: [],
        );
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rules: $data['rules'] ?? [],
            messages: $data['messages'] ?? [],
            customValidation: $data['custom_validation'] ?? [],
        );
    }

    /**
     * Check if any validation rules are defined.
     */
    public function hasRules(): bool
    {
        return !empty($this->rules);
    }

    /**
     * Convert to Laravel validation array format.
     *
     * @return array<string>
     */
    public function toValidationArray(): array
    {
        return $this->rules;
    }

    /**
     * Get custom error messages for Laravel validator.
     *
     * @return array<string, string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Merge with another ValidationRule.
     * Later rules take precedence.
     */
    public function merge(self $other): self
    {
        return new self(
            rules: array_unique([...$this->rules, ...$other->rules]),
            messages: [...$this->messages, ...$other->messages],
            customValidation: [...$this->customValidation, ...$other->customValidation],
        );
    }

    /**
     * Add additional rules.
     *
     * @param array<string> $rules
     */
    public function addRules(array $rules): self
    {
        return new self(
            rules: array_unique([...$this->rules, ...$rules]),
            messages: $this->messages,
            customValidation: $this->customValidation,
        );
    }

    /**
     * Check if a specific rule exists.
     */
    public function hasRule(string $rule): bool
    {
        // Handle parameterized rules like "max:255"
        foreach ($this->rules as $existingRule) {
            if ($existingRule === $rule || str_starts_with($existingRule, $rule . ':')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove a specific rule.
     */
    public function removeRule(string $rule): self
    {
        $filteredRules = array_filter(
            $this->rules,
            fn (string $existingRule): bool => $existingRule !== $rule && !str_starts_with($existingRule, $rule . ':')
        );

        return new self(
            rules: array_values($filteredRules),
            messages: $this->messages,
            customValidation: $this->customValidation,
        );
    }

    /**
     * Get all required-related rules.
     *
     * @return array<string>
     */
    public function getRequiredRules(): array
    {
        return array_filter(
            $this->rules,
            fn (string $rule): bool => in_array($rule, ['required', 'required_if', 'required_unless', 'required_with'], true)
                || str_starts_with($rule, 'required_')
        );
    }

    /**
     * Check if field is required.
     */
    public function isRequired(): bool
    {
        return $this->hasRule('required');
    }

    /**
     * Check if field has unique validation.
     */
    public function isUnique(): bool
    {
        return $this->hasRule('unique');
    }

    /**
     * Generate validation rules for specific field types.
     */
    public static function forFieldType(string $fieldType, array $settings = []): self
    {
        $rules = match ($fieldType) {
            'text' => self::textFieldRules($settings),
            'textarea' => self::textareaFieldRules($settings),
            'email' => self::emailFieldRules($settings),
            'phone' => self::phoneFieldRules($settings),
            'url' => self::urlFieldRules($settings),
            'number' => self::numberFieldRules($settings),
            'decimal' => self::decimalFieldRules($settings),
            'currency' => self::currencyFieldRules($settings),
            'percent' => self::percentFieldRules($settings),
            'date' => self::dateFieldRules($settings),
            'datetime' => self::datetimeFieldRules($settings),
            'time' => self::timeFieldRules($settings),
            'select', 'radio' => self::selectFieldRules($settings),
            'multiselect' => self::multiselectFieldRules($settings),
            'checkbox', 'toggle' => self::booleanFieldRules($settings),
            'file' => self::fileFieldRules($settings),
            'image' => self::imageFieldRules($settings),
            'lookup' => self::lookupFieldRules($settings),
            default => [],
        };

        return new self(rules: $rules);
    }

    private static function textFieldRules(array $settings): array
    {
        $rules = ['string'];

        if (isset($settings['min_length'])) {
            $rules[] = 'min:' . $settings['min_length'];
        }

        if (isset($settings['max_length'])) {
            $rules[] = 'max:' . $settings['max_length'];
        }

        if (isset($settings['pattern'])) {
            $rules[] = 'regex:' . $settings['pattern'];
        }

        return $rules;
    }

    private static function textareaFieldRules(array $settings): array
    {
        $rules = ['string'];

        if (isset($settings['max_length'])) {
            $rules[] = 'max:' . $settings['max_length'];
        }

        return $rules;
    }

    private static function emailFieldRules(array $settings): array
    {
        $rules = ['email'];

        if ($settings['allow_multiple'] ?? false) {
            $rules = ['string']; // Multiple emails as comma-separated string
        }

        return $rules;
    }

    private static function phoneFieldRules(array $settings): array
    {
        return ['string'];
    }

    private static function urlFieldRules(array $settings): array
    {
        return ['url'];
    }

    private static function numberFieldRules(array $settings): array
    {
        $rules = ['integer'];

        if (isset($settings['min_value'])) {
            $rules[] = 'min:' . $settings['min_value'];
        }

        if (isset($settings['max_value'])) {
            $rules[] = 'max:' . $settings['max_value'];
        }

        return $rules;
    }

    private static function decimalFieldRules(array $settings): array
    {
        $rules = ['numeric'];

        if (isset($settings['min_value'])) {
            $rules[] = 'min:' . $settings['min_value'];
        }

        if (isset($settings['max_value'])) {
            $rules[] = 'max:' . $settings['max_value'];
        }

        if (isset($settings['precision'])) {
            $rules[] = 'decimal:0,' . $settings['precision'];
        }

        return $rules;
    }

    private static function currencyFieldRules(array $settings): array
    {
        return self::decimalFieldRules($settings);
    }

    private static function percentFieldRules(array $settings): array
    {
        $rules = ['numeric', 'min:0', 'max:100'];

        if (isset($settings['precision'])) {
            $rules[] = 'decimal:0,' . $settings['precision'];
        }

        return $rules;
    }

    private static function dateFieldRules(array $settings): array
    {
        $rules = ['date'];

        if (isset($settings['min_date'])) {
            $rules[] = 'after_or_equal:' . $settings['min_date'];
        }

        if (isset($settings['max_date'])) {
            $rules[] = 'before_or_equal:' . $settings['max_date'];
        }

        return $rules;
    }

    private static function datetimeFieldRules(array $settings): array
    {
        return ['date'];
    }

    private static function timeFieldRules(array $settings): array
    {
        return ['date_format:H:i:s'];
    }

    private static function selectFieldRules(array $settings): array
    {
        return ['string'];
    }

    private static function multiselectFieldRules(array $settings): array
    {
        $rules = ['array'];

        if (isset($settings['max_selections'])) {
            $rules[] = 'max:' . $settings['max_selections'];
        }

        return $rules;
    }

    private static function booleanFieldRules(array $settings): array
    {
        return ['boolean'];
    }

    private static function fileFieldRules(array $settings): array
    {
        $rules = ['file'];

        if (isset($settings['max_file_size'])) {
            $rules[] = 'max:' . $settings['max_file_size']; // in KB
        }

        if (isset($settings['allowed_file_types']) && !empty($settings['allowed_file_types'])) {
            $rules[] = 'mimes:' . implode(',', $settings['allowed_file_types']);
        }

        return $rules;
    }

    private static function imageFieldRules(array $settings): array
    {
        $rules = ['image'];

        if (isset($settings['max_file_size'])) {
            $rules[] = 'max:' . $settings['max_file_size'];
        }

        if (isset($settings['max_width']) || isset($settings['max_height'])) {
            $dimensions = [];
            if (isset($settings['max_width'])) {
                $dimensions[] = 'max_width=' . $settings['max_width'];
            }
            if (isset($settings['max_height'])) {
                $dimensions[] = 'max_height=' . $settings['max_height'];
            }
            $rules[] = 'dimensions:' . implode(',', $dimensions);
        }

        return $rules;
    }

    private static function lookupFieldRules(array $settings): array
    {
        $rules = ['integer'];

        if (isset($settings['related_module_name'])) {
            // This would need the actual table name
            // $rules[] = 'exists:' . $settings['related_module_name'] . ',id';
        }

        return $rules;
    }

    public function jsonSerialize(): array
    {
        return [
            'rules' => $this->rules,
            'messages' => $this->messages,
            'custom_validation' => $this->customValidation,
        ];
    }
}
