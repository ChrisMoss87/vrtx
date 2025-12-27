<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Domain\User\Entities\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Handles approval workflow for blueprint transitions.
 */
class ApprovalService
{
    /**
     * Check if a transition requires approval.
     */
    public function requiresApproval(BlueprintApproval $approval): bool
    {
        return true; // If approval exists, it's required
    }

    /**
     * Create an approval request for an execution.
     */
    public function createApprovalRequest(BlueprintTransitionExecution $execution): BlueprintApprovalRequest
    {
        $transition = $execution->transition;
        $approval = $transition->approval;

        if (!$approval) {
            throw new \RuntimeException('No approval configuration found for this transition');
        }

        $request = DB::table('blueprint_approval_requests')->insertGetId([
            'approval_id' => $approval->id,
            'record_id' => $execution->record_id,
            'execution_id' => $execution->id,
            'requested_by' => $execution->executed_by,
            'status' => BlueprintApprovalRequest::STATUS_PENDING,
        ]);

        // Send notifications to approvers if configured
        if ($approval->notify_on_pending) {
            $this->notifyApprovers($request);
        }

        return $request;
    }

    /**
     * Get users who can approve the request.
     */
    public function getApprovers(BlueprintApproval $approval, int $recordId): Collection
    {
        return match ($approval->approval_type) {
            BlueprintApproval::TYPE_SPECIFIC_USERS => $this->getSpecificUserApprovers($approval),
            BlueprintApproval::TYPE_ROLE_BASED => $this->getRoleBasedApprovers($approval),
            BlueprintApproval::TYPE_MANAGER => $this->getManagerApprovers($approval, $recordId),
            BlueprintApproval::TYPE_FIELD_VALUE => $this->getFieldValueApprovers($approval, $recordId),
            default => collect(),
        };
    }

    /**
     * Get specific users as approvers.
     */
    protected function getSpecificUserApprovers(BlueprintApproval $approval): Collection
    {
        $userIds = $approval->getSpecificUserIds();
        return User::whereIn('id', $userIds)->get();
    }

    /**
     * Get users with specific roles as approvers.
     */
    protected function getRoleBasedApprovers(BlueprintApproval $approval): Collection
    {
        $roleIds = $approval->getRoleIds();
        return User::whereHas('roles', function ($query) use ($roleIds) {
            $query->whereIn('roles.id', $roleIds);
        })->get();
    }

    /**
     * Get the manager of the record owner as approver.
     */
    protected function getManagerApprovers(BlueprintApproval $approval, int $recordId): Collection
    {
        // This would need to be customized based on how manager relationships are stored
        // For now, return empty - would typically look up the owner's manager
        return collect();
    }

    /**
     * Get approver from a lookup field value.
     */
    protected function getFieldValueApprovers(BlueprintApproval $approval, int $recordId): Collection
    {
        $fieldId = $approval->getApproverFieldId();
        if (!$fieldId) {
            return collect();
        }

        // Get the field and its value from the record
        // This would need access to the record data
        return collect();
    }

    /**
     * Check if a user can approve a request.
     */
    public function canApprove(BlueprintApprovalRequest $request, int $userId): bool
    {
        $approval = $request->approval;
        $approvers = $this->getApprovers($approval, $request->record_id);

        return $approvers->contains('id', $userId);
    }

    /**
     * Approve a request.
     */
    public function approve(int $requestId, int $userId, ?string $comments = null): BlueprintApprovalRequest
    {
        $request = DB::table('blueprint_approval_requests')->where('id', $requestId)->first();

        if (!$request->isPending()) {
            throw new \RuntimeException('Request is not pending');
        }

        if (!$this->canApprove($request, $userId)) {
            throw new \RuntimeException('User is not authorized to approve this request');
        }

        return DB::transaction(function () use ($request, $userId, $comments) {
            $request->approve($userId, $comments);

            // Update the execution status
            $execution = $request->execution;
            $approval = $request->approval;

            // Check if all required approvals are received
            if ($approval->require_all) {
                $approvers = $this->getApprovers($approval, $request->record_id);
                $allApproved = $approvers->count() <= 1; // For now, single approval is enough

                if (!$allApproved) {
                    // Need more approvals
                    return $request;
                }
            }

            // All approvals received, mark execution as approved
            $execution->update([
                'status' => BlueprintTransitionExecution::STATUS_APPROVED,
            ]);

            // Send notification if configured
            if ($approval->notify_on_complete) {
                $this->notifyRequester($request, 'approved');
            }

            return $request;
        });
    }

    /**
     * Reject a request.
     */
    public function reject(int $requestId, int $userId, ?string $comments = null): BlueprintApprovalRequest
    {
        $request = DB::table('blueprint_approval_requests')->where('id', $requestId)->first();

        if (!$request->isPending()) {
            throw new \RuntimeException('Request is not pending');
        }

        if (!$this->canApprove($request, $userId)) {
            throw new \RuntimeException('User is not authorized to reject this request');
        }

        return DB::transaction(function () use ($request, $userId, $comments) {
            $request->reject($userId, $comments);

            // Update the execution status
            $execution = $request->execution;
            $execution->update([
                'status' => BlueprintTransitionExecution::STATUS_REJECTED,
                'error_message' => $comments ?? 'Approval rejected',
            ]);

            // Send notification if configured
            $approval = $request->approval;
            if ($approval->notify_on_complete) {
                $this->notifyRequester($request, 'rejected');
            }

            return $request;
        });
    }

    /**
     * Check and expire old approval requests.
     */
    public function checkExpiredApprovals(): int
    {
        $expiredCount = 0;

        $pendingRequests = DB::table('blueprint_approval_requests')->where('status', BlueprintApprovalRequest::STATUS_PENDING)
            ->with('approval')
            ->get();

        foreach ($pendingRequests as $request) {
            $approval = $request->approval;
            if (!$approval->auto_reject_days) {
                continue;
            }

            $expiresAt = $request->created_at->addDays($approval->auto_reject_days);
            if (now()->isAfter($expiresAt)) {
                $request->markExpired();

                // Update execution status
                $request->execution->update([
                    'status' => BlueprintTransitionExecution::STATUS_FAILED,
                    'error_message' => 'Approval request expired',
                ]);

                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * Get pending approvals for a user.
     */
    public function getPendingApprovalsForUser(int $userId): Collection
    {
        // Get all pending requests where user can approve
        $pendingRequests = DB::table('blueprint_approval_requests')->where('status', BlueprintApprovalRequest::STATUS_PENDING)
            ->with(['approval', 'execution.transition.blueprint.module', 'requestedBy'])
            ->get();

        return $pendingRequests->filter(function (BlueprintApprovalRequest $request) use ($userId) {
            return $this->canApprove($request, $userId);
        });
    }

    /**
     * Notify approvers about a pending request.
     */
    protected function notifyApprovers(BlueprintApprovalRequest $request): void
    {
        $approvers = $this->getApprovers($request->approval, $request->record_id);

        foreach ($approvers as $approver) {
            // Create notification (implementation depends on notification system)
            if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'type' => 'App\\Notifications\\ApprovalRequestNotification',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $approver->id,
                    'data' => json_encode([
                        'title' => 'Approval Request',
                        'message' => 'You have a new approval request pending',
                        'request_id' => $request->id,
                        'execution_id' => $request->execution_id,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Notify the requester about the approval outcome.
     */
    protected function notifyRequester(BlueprintApprovalRequest $request, string $outcome): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            \Illuminate\Support\Facades\DB::table('notifications')->insert([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => 'App\\Notifications\\ApprovalOutcomeNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $request->requested_by,
                'data' => json_encode([
                    'title' => 'Approval ' . ucfirst($outcome),
                    'message' => "Your approval request has been {$outcome}",
                    'request_id' => $request->id,
                    'outcome' => $outcome,
                    'comments' => $request->comments,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Get available approval types.
     */
    public function getApprovalTypes(): array
    {
        return BlueprintApproval::getTypes();
    }
}
