<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Services\TimeMachine\SnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Job to create daily snapshots for active records.
 * This preserves historical state for time machine functionality.
 * Should be scheduled to run once per day (e.g., 2 AM).
 */
class CreateDailySnapshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(
        SnapshotService $snapshotService,
        ModuleRepositoryInterface $moduleRepository
    ): void {
        $tenants = tenancy()->all();

        Log::info('Starting daily snapshot creation', [
            'tenant_count' => count($tenants),
        ]);

        $totalSnapshots = 0;

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $modules = $moduleRepository->findActiveModules();

                foreach ($modules as $module) {
                    $count = $snapshotService->createDailySnapshots($module);
                    $totalSnapshots += $count;

                    if ($count > 0) {
                        Log::info('Created daily snapshots', [
                            'tenant_id' => $tenant->getTenantKey(),
                            'module' => $module->apiName(),
                            'count' => $count,
                        ]);
                    }
                }

                tenancy()->end();
            } catch (\Throwable $e) {
                Log::error('Failed to create daily snapshots for tenant', [
                    'tenant_id' => $tenant->getTenantKey(),
                    'error' => $e->getMessage(),
                ]);
                tenancy()->end();
            }
        }

        Log::info('Completed daily snapshot creation', [
            'total_snapshots' => $totalSnapshots,
        ]);
    }
}
