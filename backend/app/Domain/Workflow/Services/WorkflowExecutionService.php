<?php

declare(strict_types=1);

namespace App\Domain\Workflow\Services;

use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Contracts\LoggerInterface;
use App\Domain\Workflow\Entities\Workflow;
use App\Domain\Workflow\Entities\WorkflowExecution;
use App\Domain\Workflow\Entities\WorkflowStep;
use App\Domain\Workflow\Entities\WorkflowStepLog;
use App\Domain\Workflow\Events\StepExecuted;
use App\Domain\Workflow\Events\StepFailed;
use App\Domain\Workflow\Events\WorkflowCompleted;
use App\Domain\Workflow\Events\WorkflowFailed;
use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Domain\Workflow\Repositories\WorkflowRepositoryInterface;
use App\Domain\Workflow\ValueObjects\ExecutionStatus;

/**
 * Domain service for executing workflows.
 *
 * This service handles the core execution logic for workflows,
 * including step execution, condition evaluation, and error handling.
 */
class WorkflowExecutionService
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflowRepository,
        private readonly WorkflowExecutionRepositoryInterface $executionRepository,
        private readonly ConditionEvaluationService $conditionEvaluator,
        private readonly ActionDispatcherService $actionDispatcher,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Execute a workflow for a given execution record.
     */
    public function execute(WorkflowExecution $execution, ?array $context = null): bool
    {
        $workflow = $this->workflowRepository->findById($execution->workflowId());

        if (!$workflow) {
            $this->failExecution($execution, "Workflow not found: {$execution->workflowId()}");
            return false;
        }

        $steps = $workflow->steps();

        if (empty($steps)) {
            $this->completeExecution($execution, $workflow);
            return true;
        }

        // Use provided context or get from execution
        $context = $context ?? $execution->contextData();

        // Mark as started
        $execution->markAsStarted();
        $this->executionRepository->save($execution);

        try {
            // Execute each step in order
            foreach ($steps as $step) {
                $stepLog = $this->createStepLog($execution, $step);

                // Check step conditions
                if (!$this->shouldExecuteStep($step, $context)) {
                    $this->skipStep($stepLog, $execution, 'Step conditions not met');
                    continue;
                }

                // Execute the step
                $success = $this->executeStep($step, $stepLog, $context, $execution);

                if (!$success && !$step->continueOnError()) {
                    $this->failExecution(
                        $execution,
                        "Step '{$step->name()}' failed",
                        $step->getId()
                    );
                    return false;
                }

                // Update context with step output
                $context = $this->updateContextWithStepOutput($context, $step, $stepLog);
            }

            $this->completeExecution($execution, $workflow);
            return true;

        } catch (\Exception $e) {
            $this->logger->error('Workflow execution failed', [
                'execution_id' => $execution->getId(),
                'workflow_id' => $workflow->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->failExecution($execution, $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a step should be executed based on its conditions.
     */
    private function shouldExecuteStep(WorkflowStep $step, array $context): bool
    {
        $conditions = $step->conditions()->toArray();

        if (empty($conditions)) {
            return true;
        }

        return $this->conditionEvaluator->evaluate($conditions, $context);
    }

    /**
     * Execute a single workflow step.
     */
    private function executeStep(
        WorkflowStep $step,
        WorkflowStepLog $stepLog,
        array $context,
        WorkflowExecution $execution,
    ): bool {
        $stepLog->markAsStarted($context);
        $this->executionRepository->saveStepLog($stepLog);

        try {
            // Dispatch to the appropriate action handler
            $result = $this->actionDispatcher->dispatch(
                $step->actionType(),
                $step->actionConfig()->toArray(),
                $context
            );

            $stepLog->markAsCompleted($result);
            $this->executionRepository->saveStepLog($stepLog);

            // Update execution counters
            $execution->incrementStepsCompleted();
            $this->executionRepository->save($execution);

            // Dispatch event
            $this->eventDispatcher->dispatch(new StepExecuted(
                workflowId: $execution->workflowId(),
                executionId: $execution->getId() ?? 0,
                stepId: $step->getId() ?? 0,
                actionType: $step->actionType()->value,
                durationMs: $stepLog->durationMs() ?? 0,
                output: $result,
            ));

            return true;

        } catch (\Exception $e) {
            $stepLog->markAsFailed($e->getMessage());
            $this->executionRepository->saveStepLog($stepLog);

            // Update execution counters
            $execution->incrementStepsFailed();
            $this->executionRepository->save($execution);

            // Dispatch event
            $this->eventDispatcher->dispatch(new StepFailed(
                workflowId: $execution->workflowId(),
                executionId: $execution->getId() ?? 0,
                stepId: $step->getId() ?? 0,
                actionType: $step->actionType()->value,
                errorMessage: $e->getMessage(),
                attemptNumber: $stepLog->attemptNumber(),
                willRetry: $this->shouldRetry($step, $stepLog),
            ));

            // Handle retry logic
            if ($this->shouldRetry($step, $stepLog)) {
                $this->scheduleRetry($step, $stepLog, $context);
            }

            return false;
        }
    }

    /**
     * Skip a step and record it.
     */
    private function skipStep(WorkflowStepLog $stepLog, WorkflowExecution $execution, string $reason): void
    {
        $stepLog->markAsSkipped($reason);
        $this->executionRepository->saveStepLog($stepLog);

        $execution->incrementStepsSkipped();
        $this->executionRepository->save($execution);
    }

    /**
     * Create a step log for tracking execution.
     */
    private function createStepLog(WorkflowExecution $execution, WorkflowStep $step): WorkflowStepLog
    {
        return WorkflowStepLog::create(
            executionId: $execution->getId() ?? 0,
            stepId: $step->getId() ?? 0,
            actionType: $step->actionType(),
        );
    }

    /**
     * Update context with output from a completed step.
     */
    private function updateContextWithStepOutput(array $context, WorkflowStep $step, WorkflowStepLog $stepLog): array
    {
        $output = $stepLog->outputData();

        if (!empty($output)) {
            $context['step_outputs'] = array_merge(
                $context['step_outputs'] ?? [],
                [$step->getId() => $output]
            );
        }

        return $context;
    }

    /**
     * Check if a step should be retried.
     */
    private function shouldRetry(WorkflowStep $step, WorkflowStepLog $stepLog): bool
    {
        return $stepLog->attemptNumber() < $step->retryCount();
    }

    /**
     * Schedule a retry for a failed step.
     */
    private function scheduleRetry(WorkflowStep $step, WorkflowStepLog $stepLog, array $context): void
    {
        // Create a new step log for the retry
        $retryLog = WorkflowStepLog::create(
            executionId: $stepLog->executionId(),
            stepId: $stepLog->stepId(),
            actionType: $stepLog->actionType(),
        );
        $retryLog->setAttemptNumber($stepLog->attemptNumber() + 1);

        $this->executionRepository->saveStepLog($retryLog);

        // Note: The actual retry scheduling should be handled by infrastructure
        // This would typically dispatch a job with the delay
        $this->logger->info('Step scheduled for retry', [
            'step_id' => $step->getId(),
            'attempt' => $retryLog->attemptNumber(),
            'delay_seconds' => $step->retryDelaySeconds(),
        ]);
    }

    /**
     * Complete a workflow execution successfully.
     */
    private function completeExecution(WorkflowExecution $execution, Workflow $workflow): void
    {
        $execution->markAsCompleted();
        $this->executionRepository->save($execution);

        // Update workflow statistics
        $workflow->recordExecution(true);
        $this->workflowRepository->save($workflow);

        // Dispatch event
        $this->eventDispatcher->dispatch(new WorkflowCompleted(
            workflowId: $workflow->getId() ?? 0,
            executionId: $execution->getId() ?? 0,
            stepsCompleted: $execution->stepsCompleted(),
            stepsSkipped: $execution->stepsSkipped(),
            durationMs: $execution->durationMs() ?? 0,
        ));
    }

    /**
     * Mark a workflow execution as failed.
     */
    private function failExecution(WorkflowExecution $execution, string $errorMessage, ?int $failedStepId = null): void
    {
        $execution->markAsFailed($errorMessage);
        $this->executionRepository->save($execution);

        // Update workflow statistics
        $workflow = $this->workflowRepository->findById($execution->workflowId());
        if ($workflow) {
            $workflow->recordExecution(false);
            $this->workflowRepository->save($workflow);
        }

        // Dispatch event
        $this->eventDispatcher->dispatch(new WorkflowFailed(
            workflowId: $execution->workflowId(),
            executionId: $execution->getId() ?? 0,
            errorMessage: $errorMessage,
            stepsCompleted: $execution->stepsCompleted(),
            stepsFailed: $execution->stepsFailed(),
            failedStepId: $failedStepId,
        ));
    }
}
