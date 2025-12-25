<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Blueprint;

use App\Domain\Blueprint\Events\AllApprovalsCompleted;
use App\Domain\Blueprint\Events\ApprovalRequestApproved;
use App\Domain\Blueprint\Repositories\TransitionExecutionRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use Illuminate\Support\Facades\DB;

/**
 * Checks if all required approvals are complete when a single approval is approved.
 * If all approvals are complete, dispatches AllApprovalsCompleted event.
 */
class CheckAllApprovalsListener
{
    private const STATUS_PENDING = 'pending';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TransitionExecutionRepositoryInterface $transitionExecutionRepository,
    ) {}

    public function handle(ApprovalRequestApproved $event): void
    {
        // If require_all is false, one approval is enough - dispatch completion immediately
        if (!$event->requireAll()) {
            $this->dispatchAllApprovalsCompleted($event);
            return;
        }

        // Check if all approval requests for this execution are now approved
        $execution = $this->transitionExecutionRepository->findById($event->executionId());
        if (!$execution) {
            return;
        }

        $pendingCount = DB::table('blueprint_approval_requests')
            ->where('execution_id', $event->executionId())
            ->where('status', self::STATUS_PENDING)
            ->count();

        if ($pendingCount === 0) {
            $this->dispatchAllApprovalsCompleted($event);
        }
    }

    private function dispatchAllApprovalsCompleted(ApprovalRequestApproved $event): void
    {
        $execution = $this->transitionExecutionRepository->findById($event->executionId());
        if (!$execution) {
            return;
        }

        // Get transition to find toStateId
        $transition = DB::table('blueprint_transitions')
            ->where('id', $execution->getTransitionId())
            ->first();

        if (!$transition) {
            return;
        }

        $this->eventDispatcher->dispatch(new AllApprovalsCompleted(
            executionId: $event->executionId(),
            blueprintId: $event->blueprintId(),
            transitionId: $event->transitionId(),
            recordId: $event->recordId(),
            fromStateId: $execution->getFromStateId(),
            toStateId: (int) $transition->to_state_id,
        ));
    }
}
