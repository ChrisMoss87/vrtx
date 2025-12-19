<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Rotting\DealRottingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckRottingDealsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DealRottingService $rottingService): void
    {
        Log::info('CheckRottingDealsJob: Starting rotting deal check');

        $result = $rottingService->checkAndCreateAlerts();

        Log::info('CheckRottingDealsJob: Completed', [
            'deals_checked' => $result['deals_checked'],
            'alerts_created' => $result['alerts_created'],
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CheckRottingDealsJob: Failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
