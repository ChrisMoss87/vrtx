<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Services;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintRecordState;
use App\Domain\Blueprint\Entities\BlueprintTransition;
use App\Domain\Blueprint\Entities\TransitionExecution;
use App\Domain\Blueprint\Events\TransitionExecuted;
use App\Domain\Blueprint\Events\TransitionFailed;
use App\Domain\Blueprint\Repositories\BlueprintRecordStateRepositoryInterface;
use App\Domain\Blueprint\Repositories\TransitionExecutionRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Contracts\LoggerInterface;
use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Workflow\Services\ActionDispatcherService;

/**
 * Domain service for executing blueprint transitions.
 */
class TransitionExecutionService
{
    public function __construct(
        private readonly TransitionValidationService $validationService,
        private readonly BlueprintRecordStateRepositoryInterface $recordStateRepository,
        private readonly TransitionExecutionRepositoryInterface $executionRepository,
        private readonly ActionDispatcherService $actionDispatcher,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Execute a transition for a record.
     */
    public function execute(
        Blueprint $blueprint,
        BlueprintTransition $transition,
        int $recordId,
        array $recordData,
        array $requirementData = [],
        ?int $userId = null,
    ): TransitionExecution {
        // Get or create record state
        $recordState = $this->recordStateRepository->findByRecordId($blueprint->getId(), $recordId);

        // Validate transition can be executed
        $validationResult = $this->validationService->canExecuteTransition(
            $blueprint,
            $transition,
            $recordState,
            $recordData
        );

        if (!$validationResult['valid']) {
            throw new \RuntimeException(
                'Cannot execute transition: ' . implode(', ', $validationResult['errors'])
            );
        }

        // Create execution record
        $fromStateId = $recordState?->getCurrentStateId() ?? 0;
        $execution = TransitionExecution::create(
            transitionId: $transition->getId(),
            recordId: $recordId,
            fromStateId: $fromStateId,
            toStateId: $transition->getToStateId(),
            executedBy: $userId ? UserId::fromInt($userId) : null,
        );

        $execution->start();
        $execution->setRequirementData($requirementData);
        $savedExecution = $this->executionRepository->save($execution);

        try {
            // Execute after-phase actions
            if ($transition->hasActions()) {
                $this->executeActions($transition, $recordData, $requirementData, $savedExecution);
            }

            // Update record state
            $this->updateRecordState($blueprint, $recordId, $transition->getToStateId(), $recordState);

            // Mark execution as complete
            $savedExecution->complete();
            $this->executionRepository->save($savedExecution);

            // Dispatch event
            $this->eventDispatcher->dispatch(new TransitionExecuted(
                blueprintId: $blueprint->getId(),
                transitionId: $transition->getId(),
                recordId: $recordId,
                fromStateId: $fromStateId,
                toStateId: $transition->getToStateId(),
                executedByUserId: $userId,
            ));

            $this->logger->info('Blueprint transition executed', [
                'blueprint_id' => $blueprint->getId(),
                'transition_id' => $transition->getId(),
                'record_id' => $recordId,
                'from_state' => $fromStateId,
                'to_state' => $transition->getToStateId(),
            ]);

            return $savedExecution;

        } catch (\Exception $e) {
            $savedExecution->fail($e->getMessage());
            $this->executionRepository->save($savedExecution);

            $this->eventDispatcher->dispatch(new TransitionFailed(
                blueprintId: $blueprint->getId(),
                transitionId: $transition->getId(),
                recordId: $recordId,
                errorMessage: $e->getMessage(),
                executedByUserId: $userId,
            ));

            throw $e;
        }
    }

    /**
     * Execute transition actions.
     */
    private function executeActions(
        BlueprintTransition $transition,
        array $recordData,
        array $requirementData,
        TransitionExecution $execution,
    ): void {
        $context = [
            'record' => $recordData,
            'record_id' => $recordData['id'] ?? null,
            'module_id' => $recordData['module_id'] ?? null,
            'requirement_data' => $requirementData,
            'transition_id' => $transition->getId(),
            'transition_name' => $transition->getName(),
        ];

        foreach ($transition->getActions() as $index => $actionConfig) {
            $actionType = $actionConfig['type'] ?? $actionConfig['action_type'] ?? null;
            if (!$actionType) {
                continue;
            }

            try {
                $result = $this->actionDispatcher->dispatchByName($actionType, $actionConfig, $context);
                $execution->addActionResult("action_{$index}", $result);
            } catch (\Exception $e) {
                $this->logger->warning('Blueprint action failed', [
                    'transition_id' => $transition->getId(),
                    'action_type' => $actionType,
                    'error' => $e->getMessage(),
                ]);

                // Continue with other actions unless configured to stop on error
                if ($actionConfig['stop_on_error'] ?? false) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Update the record's state in the blueprint.
     */
    private function updateRecordState(
        Blueprint $blueprint,
        int $recordId,
        int $newStateId,
        ?BlueprintRecordState $existingState,
    ): BlueprintRecordState {
        if ($existingState) {
            $existingState->transitionTo($newStateId);
            return $this->recordStateRepository->save($existingState);
        }

        $newRecordState = BlueprintRecordState::create(
            blueprintId: $blueprint->getId(),
            recordId: $recordId,
            currentStateId: $newStateId,
        );

        return $this->recordStateRepository->save($newRecordState);
    }
}
