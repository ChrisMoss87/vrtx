<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\DTOs;

/**
 * Data transfer object for executing a transition.
 */
final readonly class ExecuteTransitionDTO
{
    public function __construct(
        public int $blueprintId,
        public int $transitionId,
        public int $recordId,
        public array $requirementData = [],
        public ?string $comment = null,
        public ?int $executedBy = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            blueprintId: (int) $data['blueprint_id'],
            transitionId: (int) $data['transition_id'],
            recordId: (int) $data['record_id'],
            requirementData: $data['requirement_data'] ?? [],
            comment: $data['comment'] ?? null,
            executedBy: $data['executed_by'] ?? null,
        );
    }
}
