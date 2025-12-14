<?php

declare(strict_types=1);

namespace App\Application\Services\Blueprint;

use App\Domain\Blueprint\DTOs\BlueprintResponseDTO;
use App\Domain\Blueprint\DTOs\CreateBlueprintDTO;
use App\Domain\Blueprint\DTOs\ExecuteTransitionDTO;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintState;
use App\Domain\Blueprint\Entities\BlueprintTransition;
use App\Domain\Blueprint\Repositories\BlueprintRepositoryInterface;
use App\Domain\Blueprint\Repositories\BlueprintRecordStateRepositoryInterface;
use App\Domain\Blueprint\Services\SlaMonitoringService;
use App\Domain\Blueprint\Services\TransitionExecutionService;
use App\Domain\Blueprint\Services\TransitionValidationService;
use Illuminate\Support\Facades\DB;

/**
 * Application service for Blueprint operations.
 */
class BlueprintApplicationService
{
    public function __construct(
        private readonly BlueprintRepositoryInterface $blueprintRepository,
        private readonly BlueprintRecordStateRepositoryInterface $recordStateRepository,
        private readonly TransitionValidationService $validationService,
        private readonly TransitionExecutionService $executionService,
        private readonly SlaMonitoringService $slaMonitoringService,
    ) {}

    /**
     * Get all blueprints.
     *
     * @return BlueprintResponseDTO[]
     */
    public function getAllBlueprints(): array
    {
        $blueprints = $this->blueprintRepository->findAll();
        return array_map(fn($b) => BlueprintResponseDTO::fromEntity($b), $blueprints);
    }

    /**
     * Get a blueprint by ID.
     */
    public function getBlueprint(int $id): ?BlueprintResponseDTO
    {
        $blueprint = $this->blueprintRepository->findById($id);
        return $blueprint ? BlueprintResponseDTO::fromEntity($blueprint) : null;
    }

    /**
     * Get blueprints for a module.
     *
     * @return BlueprintResponseDTO[]
     */
    public function getBlueprintsForModule(int $moduleId): array
    {
        $blueprints = $this->blueprintRepository->findByModuleId($moduleId);
        return array_map(fn($b) => BlueprintResponseDTO::fromEntity($b), $blueprints);
    }

    /**
     * Get active blueprint for a field.
     */
    public function getActiveBlueprintForField(int $fieldId): ?BlueprintResponseDTO
    {
        $blueprint = $this->blueprintRepository->findByFieldId($fieldId);
        if (!$blueprint || !$blueprint->isActive()) {
            return null;
        }
        return BlueprintResponseDTO::fromEntity($blueprint);
    }

    /**
     * Create a new blueprint.
     */
    public function createBlueprint(CreateBlueprintDTO $dto): BlueprintResponseDTO
    {
        return DB::transaction(function () use ($dto) {
            $blueprint = Blueprint::create(
                name: $dto->name,
                moduleId: $dto->moduleId,
                fieldId: $dto->fieldId,
                description: $dto->description,
            );

            $savedBlueprint = $this->blueprintRepository->save($blueprint);

            // Create states
            foreach ($dto->states as $stateDto) {
                $state = BlueprintState::create(
                    blueprintId: $savedBlueprint->getId(),
                    name: $stateDto->name,
                    fieldOptionValue: $stateDto->fieldOptionValue,
                    color: $stateDto->color,
                    isInitial: $stateDto->isInitial,
                    isTerminal: $stateDto->isTerminal,
                    positionX: $stateDto->positionX,
                    positionY: $stateDto->positionY,
                    metadata: $stateDto->metadata,
                );
                $savedBlueprint->addState($state);
            }

            // Create transitions
            foreach ($dto->transitions as $transitionDto) {
                $transition = BlueprintTransition::create(
                    blueprintId: $savedBlueprint->getId(),
                    fromStateId: $transitionDto->fromStateId,
                    toStateId: $transitionDto->toStateId,
                    name: $transitionDto->name,
                    description: $transitionDto->description,
                    buttonLabel: $transitionDto->buttonLabel,
                    displayOrder: $transitionDto->displayOrder,
                );
                $transition->setConditions($transitionDto->conditions);
                $transition->setRequirements($transitionDto->requirements);
                $transition->setActions($transitionDto->actions);
                $transition->setApprovalConfig($transitionDto->approvalConfig);
                $savedBlueprint->addTransition($transition);
            }

            // Save again with states and transitions
            $finalBlueprint = $this->blueprintRepository->save($savedBlueprint);

            return BlueprintResponseDTO::fromEntity($finalBlueprint);
        });
    }

    /**
     * Activate a blueprint.
     */
    public function activateBlueprint(int $id): BlueprintResponseDTO
    {
        $blueprint = $this->blueprintRepository->findById($id);
        if (!$blueprint) {
            throw new \InvalidArgumentException("Blueprint not found: {$id}");
        }

        $blueprint->activate();
        $savedBlueprint = $this->blueprintRepository->save($blueprint);

        return BlueprintResponseDTO::fromEntity($savedBlueprint);
    }

    /**
     * Deactivate a blueprint.
     */
    public function deactivateBlueprint(int $id): BlueprintResponseDTO
    {
        $blueprint = $this->blueprintRepository->findById($id);
        if (!$blueprint) {
            throw new \InvalidArgumentException("Blueprint not found: {$id}");
        }

        $blueprint->deactivate();
        $savedBlueprint = $this->blueprintRepository->save($blueprint);

        return BlueprintResponseDTO::fromEntity($savedBlueprint);
    }

    /**
     * Delete a blueprint.
     */
    public function deleteBlueprint(int $id): bool
    {
        return $this->blueprintRepository->delete($id);
    }

    /**
     * Get available transitions for a record.
     */
    public function getAvailableTransitions(int $blueprintId, int $recordId, array $recordData): array
    {
        $blueprint = $this->blueprintRepository->findById($blueprintId);
        if (!$blueprint) {
            throw new \InvalidArgumentException("Blueprint not found: {$blueprintId}");
        }

        $recordState = $this->recordStateRepository->findByRecordId($blueprintId, $recordId);
        $transitions = $this->validationService->getAvailableTransitions($blueprint, $recordState, $recordData);

        return array_map(fn($t) => [
            'id' => $t->getId(),
            'name' => $t->getName(),
            'button_label' => $t->getButtonLabel(),
            'to_state_id' => $t->getToStateId(),
            'requires_approval' => $t->requiresApproval(),
            'has_requirements' => $t->hasRequirements(),
        ], $transitions);
    }

    /**
     * Execute a transition.
     */
    public function executeTransition(ExecuteTransitionDTO $dto, array $recordData): array
    {
        $blueprint = $this->blueprintRepository->findById($dto->blueprintId);
        if (!$blueprint) {
            throw new \InvalidArgumentException("Blueprint not found: {$dto->blueprintId}");
        }

        $transition = $blueprint->getTransitionById($dto->transitionId);
        if (!$transition) {
            throw new \InvalidArgumentException("Transition not found: {$dto->transitionId}");
        }

        $execution = $this->executionService->execute(
            blueprint: $blueprint,
            transition: $transition,
            recordId: $dto->recordId,
            recordData: $recordData,
            requirementData: $dto->requirementData,
            userId: $dto->executedBy,
        );

        return [
            'execution_id' => $execution->getId(),
            'status' => $execution->getStatus()->value,
            'new_state_id' => $execution->getToStateId(),
        ];
    }

    /**
     * Get record state in a blueprint.
     */
    public function getRecordState(int $blueprintId, int $recordId): ?array
    {
        $blueprint = $this->blueprintRepository->findById($blueprintId);
        if (!$blueprint) {
            return null;
        }

        $recordState = $this->recordStateRepository->findByRecordId($blueprintId, $recordId);
        if (!$recordState) {
            return null;
        }

        $currentState = $blueprint->getStateById($recordState->getCurrentStateId());
        $slaStatus = $this->slaMonitoringService->getRecordSlaStatus($blueprint, $recordState);

        return [
            'record_id' => $recordId,
            'blueprint_id' => $blueprintId,
            'current_state_id' => $recordState->getCurrentStateId(),
            'current_state_name' => $currentState?->getName(),
            'entered_state_at' => $recordState->getEnteredStateAt()->format('c'),
            'hours_in_state' => $recordState->getHoursInCurrentState(),
            'sla' => $slaStatus,
        ];
    }

    /**
     * Check SLAs for a blueprint.
     */
    public function checkBlueprintSlas(int $blueprintId): array
    {
        return $this->slaMonitoringService->checkBlueprintSlas($blueprintId);
    }
}
