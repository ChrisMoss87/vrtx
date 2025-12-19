<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\DTOs;

/**
 * Data transfer object for creating a blueprint transition.
 */
final readonly class CreateBlueprintTransitionDTO
{
    public function __construct(
        public string $name,
        public ?int $fromStateId,
        public int $toStateId,
        public ?string $description = null,
        public ?string $buttonLabel = null,
        public int $displayOrder = 0,
        public array $conditions = [],
        public array $requirements = [],
        public array $actions = [],
        public ?array $approvalConfig = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            fromStateId: isset($data['from_state_id']) ? (int) $data['from_state_id'] : null,
            toStateId: (int) $data['to_state_id'],
            description: $data['description'] ?? null,
            buttonLabel: $data['button_label'] ?? null,
            displayOrder: (int) ($data['display_order'] ?? 0),
            conditions: $data['conditions'] ?? [],
            requirements: $data['requirements'] ?? [],
            actions: $data['actions'] ?? [],
            approvalConfig: $data['approval_config'] ?? null,
        );
    }
}
