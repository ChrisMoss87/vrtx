<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Handles approval escalation, reminders, and delegation.
 */
class ApprovalEscalationService
{
    public function __construct(
        protected ApprovalService $approvalService,
    ) {}

    /**
     * Process all pending approvals for reminders, escalations, and auto-rejects.
     *
     * @return array Summary of actions taken
     */
    public function processOverdueApprovals(): array
    {
        $summary = [
            'reminders_sent' => 0,
            'escalations_processed' => 0,
            'auto_rejections' => 0,
            'errors' => [],
        ];

        $pendingRequests = BlueprintApprovalRequest::pending()
            ->with(['approval', 'execution'])
            ->get();

        foreach ($pendingRequests as $request) {
            try {
                $approval = $request->approval;

                if (!$approval) {
                    continue;
                }

                // Check for auto-reject first (highest priority)
                if ($this->shouldAutoReject($request, $approval)) {
                    $this->autoReject($request);
                    $summary['auto_rejections']++;
                    continue;
                }

                // Check for escalation
                if ($this->shouldEscalate($request, $approval)) {
                    $this->escalate($request, $approval);
                    $summary['escalations_processed']++;
                    continue;
                }

                // Check for reminder
                if ($this->shouldSendReminder($request, $approval)) {
                    $this->sendReminder($request);
                    $summary['reminders_sent']++;
                }

            } catch (\Exception $e) {
                $summary['errors'][] = [
                    'request_id' => $request->id,
                    'error' => $e->getMessage(),
                ];
                Log::error('Error processing approval escalation', [
                    'request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Check if a request should be auto-rejected.
     */
    protected function shouldAutoReject(BlueprintApprovalRequest $request, BlueprintApproval $approval): bool
    {
        if (!$approval->hasAutoReject()) {
            return false;
        }

        $daysPending = $request->created_at->diffInDays(now());
        return $daysPending >= $approval->auto_reject_days;
    }

    /**
     * Check if a request should be escalated.
     */
    protected function shouldEscalate(BlueprintApprovalRequest $request, BlueprintApproval $approval): bool
    {
        if (!$approval->hasEscalation()) {
            return false;
        }

        // Already escalated?
        if ($request->isEscalated()) {
            return false;
        }

        $hoursPending = $request->getHoursPending();
        return $hoursPending >= $approval->escalation_hours;
    }

    /**
     * Check if a reminder should be sent.
     */
    protected function shouldSendReminder(BlueprintApprovalRequest $request, BlueprintApproval $approval): bool
    {
        if (!$approval->hasReminders()) {
            return false;
        }

        // Max reminders reached?
        if ($request->reminder_count >= $approval->max_reminders) {
            return false;
        }

        $hoursSinceLastAction = $request->last_reminder_at
            ? $request->last_reminder_at->diffInHours(now())
            : $request->created_at->diffInHours(now());

        return $hoursSinceLastAction >= $approval->reminder_hours;
    }

    /**
     * Auto-reject a request.
     */
    protected function autoReject(BlueprintApprovalRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $reason = "Automatically rejected after {$request->approval->auto_reject_days} days without response";

            $request->update([
                'status' => BlueprintApprovalRequest::STATUS_EXPIRED,
                'responded_at' => now(),
                'comments' => $reason,
            ]);

            // Update execution status
            $request->execution->update([
                'status' => BlueprintTransitionExecution::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => $reason,
            ]);

            ApprovalEscalationLog::logAutoReject($request, $reason);

            // Notify the requester
            $this->notifyAutoRejection($request);

            Log::info('Approval request auto-rejected', [
                'request_id' => $request->id,
                'days_pending' => $request->approval->auto_reject_days,
            ]);
        });
    }

    /**
     * Escalate a request to the appropriate user.
     */
    protected function escalate(BlueprintApprovalRequest $request, BlueprintApproval $approval): void
    {
        DB::transaction(function () use ($request, $approval) {
            $escalationTarget = $this->findEscalationTarget($request, $approval);

            if (!$escalationTarget) {
                Log::warning('No escalation target found for approval request', [
                    'request_id' => $request->id,
                    'escalation_type' => $approval->escalation_type,
                ]);
                return;
            }

            $reason = "Escalated after {$approval->escalation_hours} hours without response";
            $request->escalateTo($escalationTarget->id, $reason);

            // Notify the escalation target
            $this->notifyEscalation($request, $escalationTarget);

            Log::info('Approval request escalated', [
                'request_id' => $request->id,
                'escalated_to' => $escalationTarget->id,
                'hours_pending' => $request->getHoursPending(),
            ]);
        });
    }

    /**
     * Send a reminder for a pending request.
     */
    protected function sendReminder(BlueprintApprovalRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $request->recordReminder();
            ApprovalEscalationLog::logReminder($request);

            // Get current approvers and notify them
            $approvers = $this->approvalService->getApprovers($request->approval, $request->record_id);
            foreach ($approvers as $approver) {
                $this->notifyReminder($request, $approver);
            }

            Log::info('Approval reminder sent', [
                'request_id' => $request->id,
                'reminder_count' => $request->reminder_count,
            ]);
        });
    }

    /**
     * Find the escalation target user.
     */
    protected function findEscalationTarget(BlueprintApprovalRequest $request, BlueprintApproval $approval): ?User
    {
        return match ($approval->escalation_type) {
            BlueprintApproval::ESCALATION_MANAGER => $this->findManagerEscalationTarget($request),
            BlueprintApproval::ESCALATION_SPECIFIC_USER => $this->findSpecificUserEscalationTarget($approval),
            BlueprintApproval::ESCALATION_ROLE => $this->findRoleBasedEscalationTarget($approval),
            default => null,
        };
    }

    /**
     * Find manager for escalation.
     */
    protected function findManagerEscalationTarget(BlueprintApprovalRequest $request): ?User
    {
        // Get the original approver's manager
        $originalApproverId = $request->original_approver_id ?? $request->requested_by;
        $originalApprover = User::find($originalApproverId);

        if (!$originalApprover || !$originalApprover->manager_id) {
            return null;
        }

        return User::find($originalApprover->manager_id);
    }

    /**
     * Find specific user for escalation.
     */
    protected function findSpecificUserEscalationTarget(BlueprintApproval $approval): ?User
    {
        $userId = $approval->getEscalationTargetUserId();
        return $userId ? User::find($userId) : null;
    }

    /**
     * Find a user with the specified role for escalation.
     */
    protected function findRoleBasedEscalationTarget(BlueprintApproval $approval): ?User
    {
        $roleIds = $approval->getEscalationRoleIds();

        if (empty($roleIds)) {
            return null;
        }

        // Get a user with the specified role(s)
        return User::whereHas('roles', function ($query) use ($roleIds) {
            $query->whereIn('roles.id', $roleIds);
        })->first();
    }

    /**
     * Create an approval request with delegation support.
     */
    public function createApprovalRequestWithDelegation(
        BlueprintTransitionExecution $execution,
        int $approverUserId
    ): BlueprintApprovalRequest {
        $transition = $execution->transition;
        $approval = $transition->approval;
        $blueprint = $transition->blueprint;

        // Check for delegation
        $delegation = ApprovalDelegation::findActiveDelegationFor($approverUserId, $blueprint->id);

        $effectiveApproverId = $delegation
            ? $delegation->delegate_id
            : $approverUserId;

        $request = DB::table('blueprint_approval_requests')->insertGetId([
            'approval_id' => $approval->id,
            'record_id' => $execution->record_id,
            'execution_id' => $execution->id,
            'requested_by' => $execution->executed_by,
            'original_approver_id' => $delegation ? $approverUserId : null,
            'delegation_id' => $delegation?->id,
            'status' => BlueprintApprovalRequest::STATUS_PENDING,
        ]);

        // Notify the effective approver
        if ($approval->notify_on_pending) {
            $effectiveApprover = User::find($effectiveApproverId);
            if ($effectiveApprover) {
                $this->notifyApprovalRequest($request, $effectiveApprover);
            }

            // Also notify delegator if configured
            if ($delegation && $delegation->notify_delegator) {
                $delegator = User::find($approverUserId);
                if ($delegator) {
                    $this->notifyDelegation($request, $delegator, $effectiveApprover);
                }
            }
        }

        return $request;
    }

    /**
     * Reassign an approval request to a different user.
     */
    public function reassign(
        BlueprintApprovalRequest $request,
        int $newApproverId,
        int $reassignedBy,
        string $reason
    ): BlueprintApprovalRequest {
        if (!$request->isPending()) {
            throw new \RuntimeException('Cannot reassign a non-pending approval request');
        }

        return DB::transaction(function () use ($request, $newApproverId, $reassignedBy, $reason) {
            $request->reassignTo($newApproverId, $reassignedBy, $reason);

            // Notify the new approver
            $newApprover = User::find($newApproverId);
            if ($newApprover) {
                $this->notifyReassignment($request, $newApprover);
            }

            Log::info('Approval request reassigned', [
                'request_id' => $request->id,
                'new_approver_id' => $newApproverId,
                'reassigned_by' => $reassignedBy,
            ]);

            return $request->fresh();
        });
    }

    /**
     * Get delegations for a user (both incoming and outgoing).
     */
    public function getDelegationsForUser(int $userId): array
    {
        return [
            'outgoing' => ApprovalDelegation::forDelegator($userId)->with('delegate')->get(),
            'incoming' => ApprovalDelegation::forDelegate($userId)->active()->with('delegator')->get(),
        ];
    }

    /**
     * Create a new delegation.
     */
    public function createDelegation(
        int $delegatorId,
        int $delegateId,
        \DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate = null,
        ?string $reason = null,
        ?array $scope = null
    ): ApprovalDelegation {
        // Check for existing active delegation
        $existing = ApprovalDelegation::active()
            ->forDelegator($delegatorId)
            ->first();

        if ($existing) {
            throw new \RuntimeException('User already has an active delegation');
        }

        // Cannot delegate to self
        if ($delegatorId === $delegateId) {
            throw new \RuntimeException('Cannot delegate to yourself');
        }

        return DB::table('approval_delegations')->insertGetId([
            'delegator_id' => $delegatorId,
            'delegate_id' => $delegateId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $reason,
            'scope' => $scope,
            'is_active' => true,
            'notify_delegator' => true,
        ]);
    }

    /**
     * End a delegation early.
     */
    public function endDelegation(int $delegationId, int $userId): void
    {
        $delegation = DB::table('approval_delegations')->where('id', $delegationId)->first();

        // Only delegator can end their own delegation
        if ($delegation->delegator_id !== $userId) {
            throw new \RuntimeException('Only the delegator can end this delegation');
        }

        $delegation->deactivate();
    }

    // Notification helpers

    protected function notifyAutoRejection(BlueprintApprovalRequest $request): void
    {
        $this->createNotification(
            $request->requested_by,
            'Approval Request Auto-Rejected',
            "Your approval request was automatically rejected due to no response after {$request->approval->auto_reject_days} days",
            ['request_id' => $request->id, 'type' => 'auto_rejection']
        );
    }

    protected function notifyEscalation(BlueprintApprovalRequest $request, User $escalationTarget): void
    {
        $this->createNotification(
            $escalationTarget->id,
            'Approval Request Escalated to You',
            'An approval request has been escalated to you for action',
            ['request_id' => $request->id, 'type' => 'escalation']
        );
    }

    protected function notifyReminder(BlueprintApprovalRequest $request, User $approver): void
    {
        $this->createNotification(
            $approver->id,
            'Approval Reminder',
            'You have a pending approval request awaiting your action',
            ['request_id' => $request->id, 'type' => 'reminder', 'reminder_count' => $request->reminder_count]
        );
    }

    protected function notifyApprovalRequest(BlueprintApprovalRequest $request, User $approver): void
    {
        $this->createNotification(
            $approver->id,
            'New Approval Request',
            'You have a new approval request pending',
            ['request_id' => $request->id, 'type' => 'new_request']
        );
    }

    protected function notifyDelegation(BlueprintApprovalRequest $request, User $delegator, User $delegate): void
    {
        $this->createNotification(
            $delegator->id,
            'Approval Delegated',
            "An approval request has been assigned to {$delegate->name} on your behalf",
            ['request_id' => $request->id, 'type' => 'delegation', 'delegate_id' => $delegate->id]
        );
    }

    protected function notifyReassignment(BlueprintApprovalRequest $request, User $newApprover): void
    {
        $this->createNotification(
            $newApprover->id,
            'Approval Request Reassigned',
            'An approval request has been reassigned to you',
            ['request_id' => $request->id, 'type' => 'reassignment']
        );
    }

    protected function createNotification(int $userId, string $title, string $message, array $data): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')->insert([
            'id' => Str::uuid()->toString(),
            'type' => 'App\\Notifications\\ApprovalNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $userId,
            'data' => json_encode([
                'title' => $title,
                'message' => $message,
                ...$data,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
