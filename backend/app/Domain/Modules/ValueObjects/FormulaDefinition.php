<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

final readonly class FormulaDefinition implements JsonSerializable
{
    /**
     * @param string $formula The formula expression
     * @param string $formulaType Type: 'calculation', 'lookup', 'date_calculation', 'text_manipulation', 'conditional'
     * @param string $returnType Return type: 'number', 'text', 'date', 'currency', 'boolean'
     * @param array<string> $dependencies List of field api_names this formula depends on
     * @param array<string> $recalculateOn List of field api_names that trigger recalculation
     */
    public function __construct(
        public string $formula,
        public string $formulaType,
        public string $returnType,
        public array $dependencies,
        public array $recalculateOn,
        public array $additionalSettings = [],
    ) {}

    public static function empty(): self
    {
        return new self(
            formula: '',
            formulaType: 'calculation',
            returnType: 'number',
            dependencies: [],
            recalculateOn: [],
            additionalSettings: [],
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            formula: $data['formula'] ?? '',
            formulaType: $data['formula_type'] ?? 'calculation',
            returnType: $data['return_type'] ?? 'number',
            dependencies: $data['dependencies'] ?? [],
            recalculateOn: $data['recalculate_on'] ?? [],
            additionalSettings: $data['additional_settings'] ?? [],
        );
    }

    public function isValid(): bool
    {
        return !empty($this->formula);
    }

    public function jsonSerialize(): array
    {
        return [
            'formula' => $this->formula,
            'formula_type' => $this->formulaType,
            'return_type' => $this->returnType,
            'dependencies' => $this->dependencies,
            'recalculate_on' => $this->recalculateOn,
            'additional_settings' => $this->additionalSettings,
        ];
    }
}
