<?php

namespace App\Jobs;

use App\Services\Duplicates\DuplicateDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScanDuplicatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
use Illuminate\Support\Facades\DB;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $moduleId,
        public ?int $limit = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DuplicateDetectionService $detectionService): void
    {
        Log::info('Starting duplicate scan', [
            'module_id' => $this->moduleId,
            'limit' => $this->limit,
        ]);

        $module = Module::find($this->moduleId);

        if (!$module) {
            Log::warning('Module not found for duplicate scan', [
                'module_id' => $this->moduleId,
            ]);
            return;
        }

        $foundCount = $detectionService->scanModuleForDuplicates(
            $module,
            $this->limit
        );

        Log::info('Duplicate scan completed', [
            'module_id' => $this->moduleId,
            'duplicates_found' => $foundCount,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Duplicate scan job failed', [
            'module_id' => $this->moduleId,
            'error' => $exception->getMessage(),
        ]);
    }
}
