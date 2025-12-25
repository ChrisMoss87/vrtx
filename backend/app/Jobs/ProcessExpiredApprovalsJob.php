<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Blueprint\ApprovalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Job to check and expire old approval requests across all tenants.
 * This job should be run hourly via the scheduler.
 */
class ProcessExpiredApprovalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(ApprovalService $approvalService): void
    {
        $tenants = DB::table('tenants')->get();

        Log::info('Starting expired approvals processing', [
            'tenant_count' => $tenants->count(),
        ]);

        $totalExpired = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($approvalService, &$totalExpired, $tenant) {
                    $expiredCount = $approvalService->checkExpiredApprovals();

                    if ($expiredCount > 0) {
                        Log::info('Expired approvals for tenant', [
                            'tenant_id' => $tenant->id,
                            'expired_count' => $expiredCount,
                        ]);
                    }

                    $totalExpired += $expiredCount;
                });
            } catch (\Throwable $e) {
                Log::error('Failed to process expired approvals for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed expired approvals processing', [
            'total_expired' => $totalExpired,
        ]);
    }
}
