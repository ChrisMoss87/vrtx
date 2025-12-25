<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Pipeline\Repositories\PipelineRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Services\Forecast\ForecastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateForecastSnapshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $periodType = 'month'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        ForecastService $forecastService,
        PipelineRepositoryInterface $pipelineRepository,
        UserRepositoryInterface $userRepository
    ): void {
        Log::info("CreateForecastSnapshotsJob: Starting {$this->periodType} snapshot creation");

        $pipelines = $pipelineRepository->findActivePipelines();
        $users = $userRepository->all();
        $snapshotsCreated = 0;

        foreach ($pipelines as $pipeline) {
            // Create pipeline-wide snapshot (no user filter)
            try {
                $forecastService->createSnapshot($pipeline->id(), null, $this->periodType);
                $snapshotsCreated++;
            } catch (\Exception $e) {
                Log::warning("CreateForecastSnapshotsJob: Failed to create pipeline snapshot", [
                    'pipeline_id' => $pipeline->id(),
                    'error' => $e->getMessage(),
                ]);
            }

            // Create per-user snapshots
            foreach ($users as $user) {
                try {
                    $forecastService->createSnapshot($pipeline->id(), $user->id(), $this->periodType);
                    $snapshotsCreated++;
                } catch (\Exception $e) {
                    Log::warning("CreateForecastSnapshotsJob: Failed to create user snapshot", [
                        'pipeline_id' => $pipeline->id(),
                        'user_id' => $user->id(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info("CreateForecastSnapshotsJob: Completed", [
            'snapshots_created' => $snapshotsCreated,
            'period_type' => $this->periodType,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CreateForecastSnapshotsJob: Failed {$this->periodType} snapshot creation", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
