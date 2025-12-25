<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Workflow\Actions\ActionHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Job to retry a failed workflow step.
 */
class RetryWorkflowStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected WorkflowStepLog $stepLog
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ActionHandler $actionHandler): void
    {
        $this->stepLog->refresh();

        // Check if step log is still in a retryable state
        if ($this->stepLog->status !== WorkflowStepLog::STATUS_PENDING) {
            Log::info('Step retry skipped - already processed', [
                'step_log_id' => $this->stepLog->id,
                'status' => $this->stepLog->status,
            ]);
            return;
        }

        $step = $this->stepLog->step;
        $execution = $this->stepLog->execution;

        if (!$step || !$execution) {
            Log::error('Step retry failed - missing step or execution', [
                'step_log_id' => $this->stepLog->id,
            ]);
            $this->stepLog->markAsFailed('Missing step or execution record');
            return;
        }

        // Get context from execution
        $context = $execution->context_data ?? [];

        try {
            $this->stepLog->markAsStarted($context);

            // Execute the action
            $result = $actionHandler->handle(
                $step->action_type,
                $step->action_config,
                $context
            );

            $this->stepLog->markAsCompleted($result);

            // Update execution counters
            $execution->incrementStepCompleted();

        } catch (\Exception $e) {
            Log::error('Step retry failed', [
                'step_log_id' => $this->stepLog->id,
                'step_id' => $step->id,
                'error' => $e->getMessage(),
            ]);

            $this->stepLog->markAsFailed($e->getMessage(), $e->getTraceAsString());

            // Update execution counters
            $execution->incrementStepFailed();

            // Check if we can retry again
            if ($this->stepLog->canRetry()) {
                $retryLog = $this->stepLog->createRetry();
                self::dispatch($retryLog)->delay($step->retry_delay_seconds ?? 60);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Step retry job permanently failed', [
            'step_log_id' => $this->stepLog->id,
            'error' => $exception->getMessage(),
        ]);

        $this->stepLog->markAsFailed("Retry job failed: {$exception->getMessage()}");
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'workflow',
            'step-retry',
            'step-log:' . $this->stepLog->id,
        ];
    }
}
