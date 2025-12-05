<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WorkflowExecution;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to execute a workflow asynchronously.
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
        protected WorkflowExecution $execution,
        protected ?array $context = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WorkflowEngine $engine): void
    {
        // Check if execution is still pending/queued
        $this->execution->refresh();

        if (!in_array($this->execution->status, [
            WorkflowExecution::STATUS_PENDING,
            WorkflowExecution::STATUS_QUEUED,
        ])) {
            Log::info('Workflow execution skipped - already processed', [
                'execution_id' => $this->execution->id,
                'status' => $this->execution->status,
            ]);
            return;
        }

        // Mark as queued if still pending
        if ($this->execution->status === WorkflowExecution::STATUS_PENDING) {
            $this->execution->update([
                'status' => WorkflowExecution::STATUS_QUEUED,
                'queued_at' => now(),
            ]);
        }

        try {
            // Use context from job if provided, otherwise from execution
            $context = $this->context ?? $this->execution->context_data;

            $engine->execute($this->execution, $context);

        } catch (\Exception $e) {
            Log::error('Workflow execution job failed', [
                'execution_id' => $this->execution->id,
                'workflow_id' => $this->execution->workflow_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed if we've exhausted retries
            if ($this->attempts() >= $this->tries) {
                $this->execution->markAsFailed("Job failed after {$this->tries} attempts: {$e->getMessage()}");
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
            'execution_id' => $this->execution->id,
            'workflow_id' => $this->execution->workflow_id,
            'error' => $exception->getMessage(),
        ]);

        $this->execution->markAsFailed("Job permanently failed: {$exception->getMessage()}");
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'workflow-execution-' . $this->execution->id;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'workflow',
            'workflow:' . $this->execution->workflow_id,
            'execution:' . $this->execution->id,
        ];
    }
}
