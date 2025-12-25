<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Import\ImportEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ValidateImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
use Illuminate\Support\Facades\DB;

    public int $tries = 1;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        public Import $import
    ) {}

    public function handle(ImportEngine $engine): void
    {
        Log::info('Starting import validation', ['import_id' => $this->import->id]);

        try {
            $engine->validate($this->import);

            Log::info('Import validation completed', [
                'import_id' => $this->import->id,
                'total_rows' => $this->import->total_rows,
                'errors' => !empty($this->import->validation_errors),
            ]);
        } catch (\Exception $e) {
            Log::error('Import validation failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ValidateImportJob failed', [
            'import_id' => $this->import->id,
            'error' => $exception->getMessage(),
        ]);

        $this->import->markAsFailed('Validation job failed: ' . $exception->getMessage());
    }
}
