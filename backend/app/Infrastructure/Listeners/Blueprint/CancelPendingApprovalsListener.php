<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Blueprint;

use App\Domain\Blueprint\Events\ApprovalRequestRejected;
use Illuminate\Support\Facades\DB;

/**
 * Cancels all other pending approvals when one approval is rejected.
 * Also updates the execution status to rejected.
 */
class CancelPendingApprovalsListener
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_EXPIRED = 'expired';
    private const STATUS_REJECTED = 'rejected';

    public function handle(ApprovalRequestRejected $event): void
    {
        // Expire all other pending approval requests for this execution
        DB::table('blueprint_approval_requests')
            ->where('execution_id', $event->executionId())
            ->where('id', '!=', $event->approvalRequestId())
            ->where('status', self::STATUS_PENDING)
            ->update([
                'status' => self::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);

        // Update the execution status to rejected
        DB::table('blueprint_transition_executions')
            ->where('id', $event->executionId())
            ->update([
                'status' => self::STATUS_REJECTED,
                'completed_at' => now(),
            ]);
    }
}
