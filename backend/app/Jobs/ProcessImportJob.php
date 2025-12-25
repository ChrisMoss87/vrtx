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

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
use Illuminate\Support\Facades\DB;

    public int $tries = 1;
    public int $timeout = 3600; // 1 hour

    public function __construct(
        public Import $import
    ) {}

    public function handle(ImportEngine $engine): void
    {
        Log::info('Starting import execution', ['import_id' => $this->import->id]);

        try {
            $engine->execute($this->import);

            Log::info('Import execution completed', [
                'import_id' => $this->import->id,
                'successful_rows' => $this->import->successful_rows,
                'failed_rows' => $this->import->failed_rows,
                'skipped_rows' => $this->import->skipped_rows,
            ]);
        } catch (\Exception $e) {
            Log::error('Import execution failed', [
                'import_id' => $this->import->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessImportJob failed', [
            'import_id' => $this->import->id,
            'error' => $exception->getMessage(),
        ]);

        $this->import->markAsFailed('Import job failed: ' . $exception->getMessage());
    }
}
