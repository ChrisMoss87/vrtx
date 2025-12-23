<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Blueprint\Events\ApprovalRequestApproved;
use App\Domain\Blueprint\Events\ApprovalRequestRejected;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Models\BlueprintApprovalRequest;
use Illuminate\Support\Facades\Log;

/**
 * Observer for BlueprintApprovalRequest model.
 *
 * This observer acts as a bridge between Eloquent model events and domain events.
 * It dispatches domain events which are handled by dedicated listeners for
 * completing transitions when approval requests are approved or rejected.
 */
class BlueprintApprovalRequestObserver
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Handle the "updated" event.
     */
    public function updated(BlueprintApprovalRequest $request): void
    {
        // Check if the status changed to approved or rejected
        if (!$request->wasChanged('status')) {
            return;
        }

        $oldStatus = $request->getOriginal('status');
        $newStatus = $request->status;

        // Only process if transitioning from pending
        if ($oldStatus !== BlueprintApprovalRequest::STATUS_PENDING) {
            return;
        }

        $execution = $request->execution;
        if (!$execution) {
            Log::warning('Approval request has no execution', [
                'request_id' => $request->id,
            ]);
            return;
        }

        $approval = $request->approval;
        $transition = $execution->transition;

        if ($newStatus === BlueprintApprovalRequest::STATUS_APPROVED) {
            $this->eventDispatcher->dispatch(new ApprovalRequestApproved(
                approvalRequestId: $request->id,
                blueprintId: $transition?->blueprint_id ?? 0,
                transitionId: $execution->transition_id,
                recordId: $execution->record_id,
                executionId: $execution->id,
                approvedByUserId: $request->responded_by ?? 0,
                comment: $request->comments,
                requireAll: $approval?->require_all ?? false,
            ));
        } elseif ($newStatus === BlueprintApprovalRequest::STATUS_REJECTED) {
            $this->eventDispatcher->dispatch(new ApprovalRequestRejected(
                approvalRequestId: $request->id,
                blueprintId: $transition?->blueprint_id ?? 0,
                transitionId: $execution->transition_id,
                recordId: $execution->record_id,
                executionId: $execution->id,
                rejectedByUserId: $request->responded_by ?? 0,
                comment: $request->comments,
            ));
        }
    }
}
