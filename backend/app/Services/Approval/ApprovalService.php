<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(
        protected ?NotificationService $notificationService = null
    ) {
        $this->notificationService = $notificationService ?? app(NotificationService::class);
    }
    public function submitForApproval(string $entityType, int $entityId, array $data = []): ?ApprovalRequest
    {
        // Find matching rule
        $rule = ApprovalRule::findMatchingRule($entityType, $data);

        if (!$rule) {
            // No rule found, no approval needed
            return null;
        }

        // Create the approval request
        $request = DB::table('approval_requests')->insertGetId([
            'rule_id' => $rule->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'title' => $data['title'] ?? "Approval for {$entityType} #{$entityId}",
            'description' => $data['description'] ?? null,
            'snapshot_data' => $data,
            'value' => $data['value'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
            'requested_by' => Auth::id(),
            'expires_at' => $rule->sla_hours
                ? now()->addHours($rule->sla_hours * count($rule->getApprovers()))
                : null,
        ]);

        // Create approval steps from rule
        $request->createStepsFromRule();

        // Submit the request
        $request->submit();

        // Send notifications
        $this->sendPendingNotifications($request);

        return $request->load(['steps.approver', 'rule']);
    }

    public function approve(ApprovalRequest $request, int $userId, ?string $comments = null): void
    {
        $this->validateApprover($request, $userId);

        $request->approve($userId, $comments);

        // Send notifications if approved
        if ($request->status === ApprovalRequest::STATUS_APPROVED) {
            $this->sendCompletionNotifications($request);
        } else {
            $this->sendPendingNotifications($request);
        }
    }

    public function reject(ApprovalRequest $request, int $userId, ?string $comments = null): void
    {
        $rule = $request->rule;

        if ($rule && $rule->require_comments && empty($comments)) {
            throw new \Exception('Comments are required when rejecting.');
        }

        $this->validateApprover($request, $userId);

        $request->reject($userId, $comments);

        // Send rejection notifications
        $this->sendRejectionNotifications($request);
    }

    public function cancel(ApprovalRequest $request, ?string $reason = null): void
    {
        if ($request->requested_by !== Auth::id()) {
            throw new \Exception('Only the requester can cancel this approval request.');
        }

        $request->cancel($reason);

        // Notify approvers
        foreach ($request->steps()->pending()->get() as $step) {
            if ($step->approver) {
                $this->queueNotification($request, $step, $step->approver_id, ApprovalNotification::TYPE_COMPLETED);
            }
        }
    }

    public function delegate(ApprovalStep $step, int $delegateId): void
    {
        $step->delegate($delegateId, Auth::id());

        // Notify delegate
        $this->queueNotification(
            $step->request,
            $step,
            $delegateId,
            ApprovalNotification::TYPE_PENDING
        );
    }

    public function setupDelegation(array $data): ApprovalDelegation
    {
        $data['delegator_id'] = Auth::id();
        $data['created_by'] = Auth::id();

        return DB::table('approval_delegations')->insertGetId($data);
    }

    public function removeDelegation(ApprovalDelegation $delegation): void
    {
        $delegation->deactivate();
    }

    public function getPendingApprovals(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return ApprovalRequest::pendingForUser($userId)
            ->with(['rule', 'requestedBy', 'steps'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function getMyRequests(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return DB::table('approval_requests')->where('requested_by', $userId)
            ->with(['rule', 'steps.approver'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function getApprovalHistory(ApprovalRequest $request): \Illuminate\Database\Eloquent\Collection
    {
        return $request->history()
            ->with(['user', 'step'])
            ->get();
    }

    public function checkNeedsApproval(string $entityType, array $data): bool
    {
        return ApprovalRule::findMatchingRule($entityType, $data) !== null;
    }

    public function getApplicableRule(string $entityType, array $data): ?ApprovalRule
    {
        return ApprovalRule::findMatchingRule($entityType, $data);
    }

    protected function validateApprover(ApprovalRequest $request, int $userId): void
    {
        $currentStep = $request->getCurrentStep();

        if (!$currentStep) {
            throw new \Exception('No pending approval step found.');
        }

        $effectiveApproverId = $currentStep->delegated_to_id ?? $currentStep->approver_id;

        // Check for delegation
        if ($currentStep->approver_id !== $userId) {
            $delegateId = ApprovalDelegation::findActiveDelegate($currentStep->approver_id, $request->rule_id);

            if ($delegateId !== $userId && $effectiveApproverId !== $userId) {
                throw new \Exception('You are not authorized to approve this request.');
            }
        }

        // Check self-approval
        $rule = $request->rule;
        if ($rule && !$rule->allow_self_approval && $request->requested_by === $userId) {
            throw new \Exception('Self-approval is not allowed for this request.');
        }
    }

    protected function sendPendingNotifications(ApprovalRequest $request): void
    {
        $currentStep = $request->getCurrentStep();

        if (!$currentStep || !$currentStep->approver) {
            return;
        }

        // Queue legacy notification
        $this->queueNotification(
            $request,
            $currentStep,
            $currentStep->approver_id,
            ApprovalNotification::TYPE_PENDING
        );

        // Send real-time notification via new notification system
        $this->notificationService->notify(
            $currentStep->approver_id,
            Notification::TYPE_APPROVAL_PENDING,
            'Approval Required: ' . $request->title,
            $request->description,
            '/approvals/' . $request->id,
            'Review Request',
            $request,
            [
                'request_id' => $request->id,
                'entity_type' => $request->entity_type,
                'entity_id' => $request->entity_id,
                'step' => $currentStep->step_order,
            ]
        );
    }

    protected function sendCompletionNotifications(ApprovalRequest $request): void
    {
        // Notify requester
        if ($request->requested_by) {
            $this->queueNotification(
                $request,
                null,
                $request->requested_by,
                ApprovalNotification::TYPE_COMPLETED
            );

            // Send real-time notification via new notification system
            $this->notificationService->notify(
                $request->requested_by,
                Notification::TYPE_APPROVAL_APPROVED,
                'Approved: ' . $request->title,
                'Your approval request has been approved.',
                '/approvals/' . $request->id,
                'View Details',
                $request,
                [
                    'request_id' => $request->id,
                    'entity_type' => $request->entity_type,
                    'entity_id' => $request->entity_id,
                ]
            );
        }
    }

    protected function sendRejectionNotifications(ApprovalRequest $request): void
    {
        // Notify requester
        if ($request->requested_by) {
            $this->queueNotification(
                $request,
                null,
                $request->requested_by,
                ApprovalNotification::TYPE_COMPLETED
            );

            // Send real-time notification via new notification system
            $this->notificationService->notify(
                $request->requested_by,
                Notification::TYPE_APPROVAL_REJECTED,
                'Rejected: ' . $request->title,
                'Your approval request has been rejected.',
                '/approvals/' . $request->id,
                'View Details',
                $request,
                [
                    'request_id' => $request->id,
                    'entity_type' => $request->entity_type,
                    'entity_id' => $request->entity_id,
                ]
            );
        }
    }

    protected function queueNotification(
        ApprovalRequest $request,
        ?ApprovalStep $step,
        int $userId,
        string $type
    ): ApprovalNotification {
        return DB::table('approval_notifications')->insertGetId([
            'request_id' => $request->id,
            'step_id' => $step?->id,
            'user_id' => $userId,
            'notification_type' => $type,
            'scheduled_at' => now(),
        ]);
    }

    public function processScheduledNotifications(): int
    {
        $notifications = ApprovalNotification::readyToSend()->get();
        $count = 0;

        foreach ($notifications as $notification) {
            try {
                $this->sendNotification($notification);
                $notification->markAsSent();
                $count++;
            } catch (\Exception $e) {
                $notification->markAsFailed();
            }
        }

        return $count;
    }

    protected function sendNotification(ApprovalNotification $notification): void
    {
        // In production, send actual notification
        // Mail::to($notification->user)->send(new ApprovalNotificationMail($notification));
    }

    public function processEscalations(): int
    {
        $overdueSteps = ApprovalStep::current()
            ->pending()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->get();

        $count = 0;

        foreach ($overdueSteps as $step) {
            $rule = $step->request->rule;

            if (!$rule || empty($rule->escalation_rules)) {
                continue;
            }

            $escalationRules = $rule->escalation_rules;

            // Handle escalation based on rules
            if (isset($escalationRules['auto_approve']) && $escalationRules['auto_approve']) {
                $step->skip('Auto-approved due to SLA breach');
                $step->request->activateNextStep();
            } elseif (isset($escalationRules['escalate_to'])) {
                // Delegate to escalation user
                $step->delegate($escalationRules['escalate_to'], 0);
            }

            $step->request->logHistory(ApprovalHistory::ACTION_ESCALATED, null, 'SLA breached - escalation triggered');
            $count++;
        }

        return $count;
    }

    // Quick Actions
    public function getQuickActions(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return ApprovalQuickAction::forUser($userId)->active()->get();
    }

    public function createQuickAction(array $data): ApprovalQuickAction
    {
        $data['user_id'] = Auth::id();
        return DB::table('approval_quick_actions')->insertGetId($data);
    }

    public function useQuickAction(ApprovalQuickAction $action, ApprovalRequest $request): void
    {
        if ($action->action_type === ApprovalQuickAction::TYPE_APPROVE) {
            $this->approve($request, $action->user_id, $action->default_comment);
        } else {
            $this->reject($request, $action->user_id, $action->default_comment);
        }
    }
}
