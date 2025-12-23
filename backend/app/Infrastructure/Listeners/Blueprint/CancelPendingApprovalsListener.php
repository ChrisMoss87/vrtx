<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Blueprint;

use App\Domain\Blueprint\Events\ApprovalRequestRejected;
use App\Models\BlueprintApprovalRequest;
use App\Models\BlueprintTransitionExecution;

/**
 * Cancels all other pending approvals when one approval is rejected.
 * Also updates the execution status to rejected.
 */
class CancelPendingApprovalsListener
{
    public function handle(ApprovalRequestRejected $event): void
    {
        // Expire all other pending approval requests for this execution
        BlueprintApprovalRequest::where('execution_id', $event->executionId())
            ->where('id', '!=', $event->approvalRequestId())
            ->where('status', BlueprintApprovalRequest::STATUS_PENDING)
            ->update([
                'status' => BlueprintApprovalRequest::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);

        // Update the execution status to rejected
        BlueprintTransitionExecution::where('id', $event->executionId())
            ->update([
                'status' => BlueprintTransitionExecution::STATUS_REJECTED,
                'completed_at' => now(),
            ]);
    }
}
