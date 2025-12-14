<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\DTOs;

/**
 * Data transfer object for creating a blueprint state.
 */
final readonly class CreateBlueprintStateDTO
{
    public function __construct(
        public string $name,
        public string $fieldOptionValue,
        public ?string $color = null,
        public bool $isInitial = false,
        public bool $isTerminal = false,
        public int $positionX = 0,
        public int $positionY = 0,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            fieldOptionValue: $data['field_option_value'],
            color: $data['color'] ?? null,
            isInitial: (bool) ($data['is_initial'] ?? false),
            isTerminal: (bool) ($data['is_terminal'] ?? false),
            positionX: (int) ($data['position_x'] ?? 0),
            positionY: (int) ($data['position_y'] ?? 0),
            metadata: $data['metadata'] ?? [],
        );
    }
}
