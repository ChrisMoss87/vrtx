<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

final readonly class ValidationRules implements JsonSerializable
{
    public function __construct(
        public array $rules,
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromArray(array $rules): self
    {
        return new self($rules);
    }

    public function add(string $rule): self
    {
        return new self([...$this->rules, $rule]);
    }

    public function toArray(): array
    {
        return $this->rules;
    }

    public function jsonSerialize(): array
    {
        return $this->rules;
    }

    public function toLaravelRules(): array
    {
        return $this->rules;
    }
}
