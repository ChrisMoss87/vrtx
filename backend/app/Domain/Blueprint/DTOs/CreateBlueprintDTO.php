<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\DTOs;

/**
 * Data transfer object for creating a new blueprint.
 */
final readonly class CreateBlueprintDTO
{
    /**
     * @param array<CreateBlueprintStateDTO> $states
     * @param array<CreateBlueprintTransitionDTO> $transitions
     */
    public function __construct(
        public string $name,
        public int $moduleId,
        public int $fieldId,
        public ?string $description = null,
        public array $layoutData = [],
        public array $states = [],
        public array $transitions = [],
        public ?int $createdBy = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            moduleId: (int) $data['module_id'],
            fieldId: (int) $data['field_id'],
            description: $data['description'] ?? null,
            layoutData: $data['layout_data'] ?? [],
            states: array_map(
                fn($s) => CreateBlueprintStateDTO::fromArray($s),
                $data['states'] ?? []
            ),
            transitions: array_map(
                fn($t) => CreateBlueprintTransitionDTO::fromArray($t),
                $data['transitions'] ?? []
            ),
            createdBy: $data['created_by'] ?? null,
        );
    }
}
