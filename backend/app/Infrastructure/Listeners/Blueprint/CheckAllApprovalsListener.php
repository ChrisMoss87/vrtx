<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Blueprint;

use App\Domain\Blueprint\Events\AllApprovalsCompleted;
use App\Domain\Blueprint\Events\ApprovalRequestApproved;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Models\BlueprintApprovalRequest;
use App\Models\BlueprintTransitionExecution;

/**
 * Checks if all required approvals are complete when a single approval is approved.
 * If all approvals are complete, dispatches AllApprovalsCompleted event.
 */
class CheckAllApprovalsListener
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(ApprovalRequestApproved $event): void
    {
        // If require_all is false, one approval is enough - dispatch completion immediately
        if (!$event->requireAll()) {
            $this->dispatchAllApprovalsCompleted($event);
            return;
        }

        // Check if all approval requests for this execution are now approved
        $execution = BlueprintTransitionExecution::find($event->executionId());
        if (!$execution) {
            return;
        }

        $pendingCount = BlueprintApprovalRequest::where('execution_id', $event->executionId())
            ->where('status', BlueprintApprovalRequest::STATUS_PENDING)
            ->count();

        if ($pendingCount === 0) {
            $this->dispatchAllApprovalsCompleted($event);
        }
    }

    private function dispatchAllApprovalsCompleted(ApprovalRequestApproved $event): void
    {
        $execution = BlueprintTransitionExecution::with('transition')->find($event->executionId());
        if (!$execution) {
            return;
        }

        $this->eventDispatcher->dispatch(new AllApprovalsCompleted(
            executionId: $event->executionId(),
            blueprintId: $event->blueprintId(),
            transitionId: $event->transitionId(),
            recordId: $event->recordId(),
            fromStateId: $execution->from_state_id,
            toStateId: $execution->transition->to_state_id,
        ));
    }
}
