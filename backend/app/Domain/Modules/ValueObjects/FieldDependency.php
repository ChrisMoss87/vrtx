<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use JsonSerializable;

final readonly class FieldDependency implements JsonSerializable
{
    public function __construct(
        public ?string $dependsOn,
        public ?DependencyFilter $filter,
    ) {}

    public static function none(): self
    {
        return new self(
            dependsOn: null,
            filter: null,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            dependsOn: $data['depends_on'] ?? null,
            filter: isset($data['filter']) ? DependencyFilter::fromArray($data['filter']) : null,
        );
    }

    public function hasDependency(): bool
    {
        return $this->dependsOn !== null;
    }

    public function jsonSerialize(): array
    {
        if (!$this->hasDependency()) {
            return [];
        }

        return array_filter([
            'depends_on' => $this->dependsOn,
            'filter' => $this->filter?->jsonSerialize(),
        ], fn ($value): bool => $value !== null);
    }
}
