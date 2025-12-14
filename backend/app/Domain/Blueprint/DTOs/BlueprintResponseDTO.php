<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\DTOs;

use App\Domain\Blueprint\Entities\Blueprint;

/**
 * Data transfer object for blueprint responses.
 */
final readonly class BlueprintResponseDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public int $moduleId,
        public int $fieldId,
        public ?string $description,
        public bool $isActive,
        public array $layoutData,
        public array $states,
        public array $transitions,
        public array $slas,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromEntity(Blueprint $blueprint): self
    {
        return new self(
            id: $blueprint->getId(),
            name: $blueprint->getName(),
            moduleId: $blueprint->getModuleId(),
            fieldId: $blueprint->getFieldId(),
            description: $blueprint->getDescription(),
            isActive: $blueprint->isActive(),
            layoutData: $blueprint->getLayoutData(),
            states: array_map(
                fn($state) => [
                    'id' => $state->getId(),
                    'name' => $state->getName(),
                    'field_option_value' => $state->getFieldOptionValue(),
                    'color' => $state->getColor(),
                    'is_initial' => $state->isInitial(),
                    'is_terminal' => $state->isTerminal(),
                    'position_x' => $state->getPositionX(),
                    'position_y' => $state->getPositionY(),
                    'metadata' => $state->getMetadata(),
                ],
                $blueprint->getStates()
            ),
            transitions: array_map(
                fn($transition) => [
                    'id' => $transition->getId(),
                    'name' => $transition->getName(),
                    'from_state_id' => $transition->getFromStateId(),
                    'to_state_id' => $transition->getToStateId(),
                    'description' => $transition->getDescription(),
                    'button_label' => $transition->getButtonLabel(),
                    'display_order' => $transition->getDisplayOrder(),
                    'is_active' => $transition->isActive(),
                    'conditions' => $transition->getConditions(),
                    'requirements' => $transition->getRequirements(),
                    'actions' => $transition->getActions(),
                    'approval_config' => $transition->getApprovalConfig(),
                    'requires_approval' => $transition->requiresApproval(),
                ],
                $blueprint->getTransitions()
            ),
            slas: array_map(
                fn($sla) => [
                    'id' => $sla->getId(),
                    'state_id' => $sla->getStateId(),
                    'name' => $sla->getName(),
                    'duration_hours' => $sla->getDurationHours(),
                    'warning_hours' => $sla->getWarningHours(),
                    'business_hours_only' => $sla->isBusinessHoursOnly(),
                    'escalation_config' => $sla->getEscalationConfig(),
                    'is_active' => $sla->isActive(),
                ],
                $blueprint->getSlas()
            ),
            createdAt: $blueprint->getCreatedAt()?->format('c'),
            updatedAt: $blueprint->getUpdatedAt()?->format('c'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'module_id' => $this->moduleId,
            'field_id' => $this->fieldId,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'layout_data' => $this->layoutData,
            'states' => $this->states,
            'transitions' => $this->transitions,
            'slas' => $this->slas,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
