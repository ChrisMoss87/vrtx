<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Workflow\Repositories\WorkflowExecutionRepositoryInterface;
use App\Domain\Workflow\Services\WorkflowExecutionService;
use App\Domain\Workflow\ValueObjects\ExecutionStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to execute a workflow asynchronously using DDD services.
 */
class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $executionId,
        protected ?array $context = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        WorkflowExecutionRepositoryInterface $executionRepository,
        WorkflowExecutionService $executionService,
    ): void {
        // Load the execution from the repository
        $execution = $executionRepository->findById($this->executionId);

        if (!$execution) {
            Log::warning('Workflow execution not found', [
                'execution_id' => $this->executionId,
            ]);
            return;
        }

        // Check if execution is still pending/queued
        if (!in_array($execution->status(), [
            ExecutionStatus::PENDING,
            ExecutionStatus::QUEUED,
        ])) {
            Log::info('Workflow execution skipped - already processed', [
                'execution_id' => $this->executionId,
                'status' => $execution->status()->value,
            ]);
            return;
        }

        // Mark as queued if still pending
        if ($execution->status() === ExecutionStatus::PENDING) {
            $execution->markAsQueued();
            $executionRepository->save($execution);
        }

        try {
            // Execute the workflow using domain service
            $executionService->execute($execution, $this->context);

        } catch (\Exception $e) {
            Log::error('Workflow execution job failed', [
                'execution_id' => $this->executionId,
                'workflow_id' => $execution->workflowId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed if we've exhausted retries
            if ($this->attempts() >= $this->tries) {
                $execution->markAsFailed("Job failed after {$this->tries} attempts: {$e->getMessage()}");
                $executionRepository->save($execution);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Workflow execution job permanently failed', [
            'execution_id' => $this->executionId,
            'error' => $exception->getMessage(),
        ]);

        // Try to mark the execution as failed
        try {
            $executionRepository = app(WorkflowExecutionRepositoryInterface::class);
            $execution = $executionRepository->findById($this->executionId);

            if ($execution) {
                $execution->markAsFailed("Job permanently failed: {$exception->getMessage()}");
                $executionRepository->save($execution);
            }
        } catch (\Exception $e) {
            Log::error('Failed to mark execution as failed', [
                'execution_id' => $this->executionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'workflow-execution-' . $this->executionId;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'workflow',
            'execution:' . $this->executionId,
        ];
    }
}
