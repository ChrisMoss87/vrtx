<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Module;
use App\Models\Tenant;
use App\Services\TimeMachine\SnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
    public function handle(SnapshotService $snapshotService): void
    {
        $tenants = Tenant::all();

        Log::info('Starting daily snapshot creation', [
            'tenant_count' => $tenants->count(),
        ]);

        $totalSnapshots = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($snapshotService, &$totalSnapshots, $tenant) {
                    $modules = Module::where('is_active', true)->get();

                    foreach ($modules as $module) {
                        $count = $snapshotService->createDailySnapshots($module);
                        $totalSnapshots += $count;

                        if ($count > 0) {
                            Log::info('Created daily snapshots', [
                                'tenant_id' => $tenant->id,
                                'module' => $module->api_name,
                                'count' => $count,
                            ]);
                        }
                    }
                });
            } catch (\Throwable $e) {
                Log::error('Failed to create daily snapshots for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed daily snapshot creation', [
            'total_snapshots' => $totalSnapshots,
        ]);
    }
}
