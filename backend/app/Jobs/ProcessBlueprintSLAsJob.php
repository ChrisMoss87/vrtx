<?php

declare(strict_types=1);

namespace App\Jobs;

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
        $tenants = tenancy()->all();

        Log::info('Starting Blueprint SLA processing', [
            'tenant_count' => count($tenants),
        ]);

        $totalResults = [
            'tenants_processed' => 0,
            'total_checked' => 0,
            'total_escalations' => 0,
            'total_breaches' => 0,
        ];

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $results = $slaService->checkSLAs();

                $totalResults['tenants_processed']++;
                $totalResults['total_checked'] += $results['checked'];
                $totalResults['total_escalations'] += $results['escalations_triggered'];
                $totalResults['total_breaches'] += $results['breaches_marked'];

                if ($results['checked'] > 0) {
                    Log::info('Processed SLAs for tenant', [
                        'tenant_id' => $tenant->getTenantKey(),
                        'checked' => $results['checked'],
                        'escalations_triggered' => $results['escalations_triggered'],
                        'breaches_marked' => $results['breaches_marked'],
                    ]);
                }

                tenancy()->end();
            } catch (\Throwable $e) {
                Log::error('Failed to process SLAs for tenant', [
                    'tenant_id' => $tenant->getTenantKey(),
                    'error' => $e->getMessage(),
                ]);
                tenancy()->end();
            }
        }

        Log::info('Completed Blueprint SLA processing', $totalResults);
    }
}
