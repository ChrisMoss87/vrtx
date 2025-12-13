<?php

declare(strict_types=1);

namespace App\Application\Services\Workflow;

use App\Domain\Shared\ValueObjects\UserId;
use App\Domain\Workflow\DTOs\CreateWorkflowDTO;
use App\Domain\Workflow\DTOs\CreateWorkflowStepDTO;
use App\Domain\Workflow\DTOs\ExecutionContextDTO;
use App\Domain\Workflow\DTOs\UpdateWorkflowDTO;
use App\Domain\Workflow\DTOs\WorkflowResponseDTO;
use App\Domain\Workflow\Entities\Workflow;
use App\Domain\Workflow\Entities\WorkflowExecution;
use App\Domain\Workflow\Entities\WorkflowStep;
use App\Domain\Workflow\Events\WorkflowCompleted;
use App\Domain\Workflow\Events\WorkflowFailed;
use App\Domain\Workflow\Events\WorkflowTriggered;
use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Domain\Workflow\Repositories\WorkflowRepositoryInterface;
use App\Domain\Workflow\Services\WorkflowTriggerEvaluatorService;
use App\Domain\Workflow\Services\WorkflowValidationService;
use App\Domain\Workflow\ValueObjects\TriggerType;
use App\Jobs\ExecuteWorkflowJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Application Service for Workflow operations.
 *
 * This service orchestrates domain operations, handles transactions,
 * and dispatches domain events. It serves as the main entry point
 * for workflow-related use cases.
 */
class WorkflowApplicationService
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflowRepository,
        private readonly WorkflowExecutionRepositoryInterface $executionRepository,
        private readonly WorkflowTriggerEvaluatorService $triggerEvaluator,
        private readonly WorkflowValidationService $validationService,
    ) {}

    /**
     * Get all workflows.
     *
     * @return array<WorkflowResponseDTO>
     */
    public function getAllWorkflows(): array
    {
        $workflows = $this->workflowRepository->findAll();
        return array_map(fn($w) => WorkflowResponseDTO::fromEntity($w), $workflows);
    }

    /**
     * Get a workflow by ID.
     */
    public function getWorkflow(int $id): ?WorkflowResponseDTO
    {
        $workflow = $this->workflowRepository->findById($id);

        if (!$workflow) {
            return null;
        }

        return WorkflowResponseDTO::fromEntity($workflow);
    }

    /**
     * Get active workflows for a module.
     *
     * @return array<WorkflowResponseDTO>
     */
    public function getActiveWorkflowsForModule(int $moduleId): array
    {
        $workflows = $this->workflowRepository->findActiveForModule($moduleId);
        return array_map(fn($w) => WorkflowResponseDTO::fromEntity($w), $workflows);
    }

    /**
     * Create a new workflow.
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function createWorkflow(CreateWorkflowDTO $dto): WorkflowResponseDTO
    {
        // Validate the workflow
        if (!$this->validationService->validateCreate($dto)) {
            $errors = $this->validationService->getErrors();
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        return DB::transaction(function () use ($dto) {
            // Create the workflow entity
            $workflow = Workflow::create(
                name: $dto->name,
                moduleId: $dto->moduleId,
                triggerType: $dto->triggerType,
                description: $dto->description,
                triggerConfig: $dto->triggerConfig,
                triggerTiming: $dto->triggerTiming,
                watchedFields: $dto->watchedFields,
                conditions: $dto->conditions,
                createdBy: $dto->createdBy ? UserId::fromInt($dto->createdBy) : null,
            );

            // Configure execution settings
            $workflow->configureExecution(
                priority: $dto->priority,
                stopOnFirstMatch: $dto->stopOnFirstMatch,
                maxExecutionsPerDay: $dto->maxExecutionsPerDay,
                runOncePerRecord: $dto->runOncePerRecord,
                allowManualTrigger: $dto->allowManualTrigger,
                delaySeconds: $dto->delaySeconds,
            );

            // Configure scheduling if applicable
            if ($dto->scheduleCron !== null) {
                $workflow->configureSchedule($dto->scheduleCron);
            }

            // Create steps
            $steps = array_map(fn($stepDto) => $this->createStepFromDTO($stepDto), $dto->steps);
            $workflow->setSteps($steps);

            // Save the workflow
            $savedWorkflow = $this->workflowRepository->save($workflow);

            return WorkflowResponseDTO::fromEntity($savedWorkflow);
        });
    }

    /**
     * Update an existing workflow.
     *
     * @throws \InvalidArgumentException If workflow not found or validation fails
     */
    public function updateWorkflow(UpdateWorkflowDTO $dto): WorkflowResponseDTO
    {
        $workflow = $this->workflowRepository->findById($dto->id);

        if (!$workflow) {
            throw new \InvalidArgumentException("Workflow not found: {$dto->id}");
        }

        return DB::transaction(function () use ($workflow, $dto) {
            // Update basic info
            $workflow->update(
                name: $dto->name,
                description: $dto->description,
                triggerType: $dto->triggerType,
                triggerConfig: $dto->triggerConfig,
                triggerTiming: $dto->triggerTiming,
                watchedFields: $dto->watchedFields,
                conditions: $dto->conditions,
                updatedBy: $dto->updatedBy ? UserId::fromInt($dto->updatedBy) : null,
            );

            // Update execution settings
            $workflow->configureExecution(
                priority: $dto->priority,
                stopOnFirstMatch: $dto->stopOnFirstMatch,
                maxExecutionsPerDay: $dto->maxExecutionsPerDay,
                runOncePerRecord: $dto->runOncePerRecord,
                allowManualTrigger: $dto->allowManualTrigger,
                delaySeconds: $dto->delaySeconds,
            );

            // Update active status if provided
            if ($dto->isActive !== null) {
                if ($dto->isActive) {
                    $workflow->activate();
                } else {
                    $workflow->deactivate();
                }
            }

            // Update scheduling
            $workflow->configureSchedule($dto->scheduleCron);

            // Update steps if provided
            if ($dto->steps !== null) {
                $steps = array_map(fn($stepDto) => $this->createStepFromDTO($stepDto), $dto->steps);
                $workflow->setSteps($steps);
            }

            // Save the workflow
            $savedWorkflow = $this->workflowRepository->save($workflow);

            return WorkflowResponseDTO::fromEntity($savedWorkflow);
        });
    }

    /**
     * Delete a workflow.
     */
    public function deleteWorkflow(int $id): bool
    {
        return $this->workflowRepository->delete($id);
    }

    /**
     * Activate a workflow.
     */
    public function activateWorkflow(int $id): WorkflowResponseDTO
    {
        $workflow = $this->workflowRepository->findById($id);

        if (!$workflow) {
            throw new \InvalidArgumentException("Workflow not found: {$id}");
        }

        $workflow->activate();
        $savedWorkflow = $this->workflowRepository->save($workflow);

        return WorkflowResponseDTO::fromEntity($savedWorkflow);
    }

    /**
     * Deactivate a workflow.
     */
    public function deactivateWorkflow(int $id): WorkflowResponseDTO
    {
        $workflow = $this->workflowRepository->findById($id);

        if (!$workflow) {
            throw new \InvalidArgumentException("Workflow not found: {$id}");
        }

        $workflow->deactivate();
        $savedWorkflow = $this->workflowRepository->save($workflow);

        return WorkflowResponseDTO::fromEntity($savedWorkflow);
    }

    /**
     * Find workflows that should be triggered by an event.
     *
     * @return array<Workflow>
     */
    public function findTriggeredWorkflows(
        int $moduleId,
        string $eventType,
        ?array $recordData = null,
        ?array $oldData = null,
        bool $isCreate = false,
    ): array {
        $activeWorkflows = $this->workflowRepository->findActiveForModule($moduleId);

        return array_filter(
            $activeWorkflows,
            fn($workflow) => $this->triggerEvaluator->shouldTrigger(
                $workflow,
                $eventType,
                $recordData,
                $oldData,
                $isCreate
            )
        );
    }

    /**
     * Create an execution for a workflow.
     *
     * @param bool $dispatchJob Whether to dispatch the job immediately
     * @param int $delaySeconds Delay before executing (0 for immediate)
     */
    public function createExecution(
        int $workflowId,
        string $triggerType,
        ?int $recordId = null,
        ?string $recordType = null,
        array $contextData = [],
        ?int $triggeredByUserId = null,
        bool $dispatchJob = true,
        int $delaySeconds = 0,
    ): WorkflowExecution {
        $execution = WorkflowExecution::create(
            workflowId: $workflowId,
            triggerType: $triggerType,
            triggerRecordId: $recordId,
            triggerRecordType: $recordType,
            contextData: $contextData,
            triggeredBy: $triggeredByUserId ? UserId::fromInt($triggeredByUserId) : null,
        );

        $savedExecution = $this->executionRepository->save($execution);

        // Dispatch event
        $this->dispatchEvent(new WorkflowTriggered(
            workflowId: $workflowId,
            executionId: $savedExecution->getId() ?? 0,
            triggerType: $triggerType,
            recordId: $recordId,
            recordType: $recordType,
            triggeredByUserId: $triggeredByUserId,
        ));

        // Dispatch execution job if requested
        if ($dispatchJob && $savedExecution->getId() !== null) {
            $job = new ExecuteWorkflowJob($savedExecution->getId(), $contextData);

            if ($delaySeconds > 0) {
                $job->delay($delaySeconds);
            }

            dispatch($job);
        }

        return $savedExecution;
    }

    /**
     * Mark an execution as started.
     */
    public function startExecution(int $executionId): WorkflowExecution
    {
        $execution = $this->executionRepository->findById($executionId);

        if (!$execution) {
            throw new \InvalidArgumentException("Execution not found: {$executionId}");
        }

        $execution->markAsStarted();
        return $this->executionRepository->save($execution);
    }

    /**
     * Mark an execution as completed.
     */
    public function completeExecution(int $executionId): WorkflowExecution
    {
        $execution = $this->executionRepository->findById($executionId);

        if (!$execution) {
            throw new \InvalidArgumentException("Execution not found: {$executionId}");
        }

        $execution->markAsCompleted();
        $savedExecution = $this->executionRepository->save($execution);

        // Update workflow statistics
        $workflow = $this->workflowRepository->findById($execution->workflowId());
        if ($workflow) {
            $workflow->recordExecution(true);
            $this->workflowRepository->save($workflow);
        }

        // Dispatch event
        $this->dispatchEvent(new WorkflowCompleted(
            workflowId: $execution->workflowId(),
            executionId: $executionId,
            stepsCompleted: $execution->stepsCompleted(),
            stepsSkipped: $execution->stepsSkipped(),
            durationMs: $execution->durationMs() ?? 0,
        ));

        return $savedExecution;
    }

    /**
     * Mark an execution as failed.
     */
    public function failExecution(int $executionId, string $errorMessage, ?int $failedStepId = null): WorkflowExecution
    {
        $execution = $this->executionRepository->findById($executionId);

        if (!$execution) {
            throw new \InvalidArgumentException("Execution not found: {$executionId}");
        }

        $execution->markAsFailed($errorMessage);
        $savedExecution = $this->executionRepository->save($execution);

        // Update workflow statistics
        $workflow = $this->workflowRepository->findById($execution->workflowId());
        if ($workflow) {
            $workflow->recordExecution(false);
            $this->workflowRepository->save($workflow);
        }

        // Dispatch event
        $this->dispatchEvent(new WorkflowFailed(
            workflowId: $execution->workflowId(),
            executionId: $executionId,
            errorMessage: $errorMessage,
            stepsCompleted: $execution->stepsCompleted(),
            stepsFailed: $execution->stepsFailed(),
            failedStepId: $failedStepId,
        ));

        return $savedExecution;
    }

    /**
     * Get execution statistics for a workflow.
     *
     * @return array{total: int, completed: int, failed: int, cancelled: int, avg_duration_ms: float}
     */
    public function getWorkflowStatistics(int $workflowId, ?int $daysSince = null): array
    {
        $since = $daysSince !== null
            ? new \DateTimeImmutable("-{$daysSince} days")
            : null;

        return $this->executionRepository->getStatisticsForWorkflow($workflowId, $since);
    }

    /**
     * Create a WorkflowStep from a DTO.
     */
    private function createStepFromDTO(CreateWorkflowStepDTO $dto): WorkflowStep
    {
        return WorkflowStep::create(
            actionType: $dto->actionType,
            order: $dto->order,
            name: $dto->name,
            actionConfig: $dto->actionConfig,
            conditions: $dto->conditions,
            branchId: $dto->branchId,
            isParallel: $dto->isParallel,
            continueOnError: $dto->continueOnError,
            retryCount: $dto->retryCount,
            retryDelaySeconds: $dto->retryDelaySeconds,
        );
    }

    /**
     * Dispatch a domain event.
     */
    private function dispatchEvent(object $event): void
    {
        Event::dispatch($event);
    }
}
