<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Blueprint\SLAService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to process Blueprint SLAs across all tenants.
 * Checks for SLA breaches and triggers escalations.
 * This job should be run every minute via the scheduler.
 */
class ProcessBlueprintSLAsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(SLAService $slaService): void
    {
        $tenants = Tenant::all();

        Log::info('Starting Blueprint SLA processing', [
            'tenant_count' => $tenants->count(),
        ]);

        $totalResults = [
            'tenants_processed' => 0,
            'total_checked' => 0,
            'total_escalations' => 0,
            'total_breaches' => 0,
        ];

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($slaService, &$totalResults, $tenant) {
                    $results = $slaService->checkSLAs();

                    $totalResults['tenants_processed']++;
                    $totalResults['total_checked'] += $results['checked'];
                    $totalResults['total_escalations'] += $results['escalations_triggered'];
                    $totalResults['total_breaches'] += $results['breaches_marked'];

                    if ($results['checked'] > 0) {
                        Log::info('Processed SLAs for tenant', [
                            'tenant_id' => $tenant->id,
                            'checked' => $results['checked'],
                            'escalations_triggered' => $results['escalations_triggered'],
                            'breaches_marked' => $results['breaches_marked'],
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                Log::error('Failed to process SLAs for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed Blueprint SLA processing', $totalResults);
    }
}
