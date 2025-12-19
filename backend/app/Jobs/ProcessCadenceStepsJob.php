<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Cadence\CadenceExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to process due cadence steps.
 *
 * This job should be scheduled to run every minute via the scheduler.
 * It finds all active cadence enrollments that are due for their next step
 * and processes them.
 */
class ProcessCadenceStepsJob implements ShouldQueue
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
     * Execute the job.
     */
    public function handle(CadenceExecutionService $executionService): void
    {
        $startTime = microtime(true);

        Log::info('Processing due cadence steps...');

        try {
            $stats = $executionService->processDueSteps();

            $duration = round((microtime(true) - $startTime) * 1000);

            Log::info('Cadence step processing completed', [
                'processed' => $stats['processed'],
                'succeeded' => $stats['succeeded'],
                'failed' => $stats['failed'],
                'skipped' => $stats['skipped'],
                'duration_ms' => $duration,
            ]);

        } catch (\Exception $e) {
            Log::error('Cadence step processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessCadenceStepsJob permanently failed', [
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['cadence', 'step-processing'];
    }
}
