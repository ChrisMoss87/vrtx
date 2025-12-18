<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlueprintApproval;
use App\Models\BlueprintApprovalRequest;
use App\Models\BlueprintRecordState;
use App\Models\BlueprintTransitionExecution;
use App\Services\Blueprint\BlueprintEngine;
use App\Services\Blueprint\SLAService;
use Illuminate\Support\Facades\Log;

/**
 * Observer for BlueprintApprovalRequest model.
 *
 * Handles the completion of transitions when approval requests are
 * approved or rejected.
 */
class BlueprintApprovalRequestObserver
{
    public function __construct(
        protected BlueprintEngine $blueprintEngine,
        protected SLAService $slaService,
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

        if ($newStatus === BlueprintApprovalRequest::STATUS_APPROVED) {
            $this->handleApproval($request);
        } elseif ($newStatus === BlueprintApprovalRequest::STATUS_REJECTED) {
            $this->handleRejection($request);
        }
    }

    /**
     * Handle an approval.
     */
    protected function handleApproval(BlueprintApprovalRequest $request): void
    {
        $execution = $request->execution;
        if (!$execution) {
            Log::warning('Approval request has no execution', [
                'request_id' => $request->id,
            ]);
            return;
        }

        $approval = $request->approval;
        if (!$approval) {
            Log::warning('Approval request has no approval config', [
                'request_id' => $request->id,
            ]);
            return;
        }

        // Check if we need all approvers or just one
        if ($approval->require_all) {
            // Check if all approval requests for this execution are approved
            $pendingCount = BlueprintApprovalRequest::where('execution_id', $execution->id)
                ->where('status', BlueprintApprovalRequest::STATUS_PENDING)
                ->count();

            if ($pendingCount > 0) {
                Log::info('Approval received but still waiting for more approvals', [
                    'request_id' => $request->id,
                    'execution_id' => $execution->id,
                    'pending_count' => $pendingCount,
                ]);
                return;
            }
        }

        // All required approvals received - proceed with transition
        $this->completeTransition($execution, $request);
    }

    /**
     * Handle a rejection.
     */
    protected function handleRejection(BlueprintApprovalRequest $request): void
    {
        $execution = $request->execution;
        if (!$execution) {
            return;
        }

        // Cancel any other pending approval requests for this execution
        BlueprintApprovalRequest::where('execution_id', $execution->id)
            ->where('id', '!=', $request->id)
            ->where('status', BlueprintApprovalRequest::STATUS_PENDING)
            ->update([
                'status' => BlueprintApprovalRequest::STATUS_EXPIRED,
                'responded_at' => now(),
            ]);

        // Update execution status to rejected
        $execution->update([
            'status' => BlueprintTransitionExecution::STATUS_REJECTED,
            'completed_at' => now(),
            'error_message' => $request->comments ?? 'Approval request rejected',
        ]);

        Log::info('Blueprint transition rejected', [
            'execution_id' => $execution->id,
            'record_id' => $execution->record_id,
            'rejected_by' => $request->responded_by,
            'comments' => $request->comments,
        ]);

        // Send rejection notification if configured
        if ($request->approval?->notify_on_complete) {
            $this->sendRejectionNotification($execution, $request);
        }
    }

    /**
     * Complete the transition after approval.
     */
    protected function completeTransition(BlueprintTransitionExecution $execution, BlueprintApprovalRequest $request): void
    {
        try {
            $execution->load(['transition.blueprint', 'transition.toState', 'transition.actions']);

            // Mark execution as approved
            $execution->update([
                'status' => BlueprintTransitionExecution::STATUS_APPROVED,
            ]);

            // Execute after-transition actions
            $actionResults = $this->executeAfterActions($execution);

            // Update the record's state
            $this->updateRecordState($execution);

            // Complete SLA for the old state and start new one
            $transition = $execution->transition;
            $blueprint = $transition->blueprint;

            $this->slaService->completeSLA($execution->record_id, $blueprint->id);

            // Start SLA for new state if applicable
            $toState = $transition->toState;
            if ($toState && $toState->sla && $toState->sla->is_active) {
                $this->slaService->startSLA($execution->record_id, $toState);
            }

            // Mark execution as completed
            $execution->update([
                'status' => BlueprintTransitionExecution::STATUS_COMPLETED,
                'completed_at' => now(),
                'action_results' => $actionResults,
            ]);

            Log::info('Blueprint transition completed after approval', [
                'execution_id' => $execution->id,
                'record_id' => $execution->record_id,
                'from_state_id' => $execution->from_state_id,
                'to_state_id' => $execution->to_state_id,
            ]);

            // Send completion notification if configured
            if ($request->approval?->notify_on_complete) {
                $this->sendCompletionNotification($execution);
            }

        } catch (\Exception $e) {
            Log::error('Failed to complete transition after approval', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);

            $execution->update([
                'status' => BlueprintTransitionExecution::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Execute after-transition actions.
     */
    protected function executeAfterActions(BlueprintTransitionExecution $execution): array
    {
        $results = [];
        $actions = $execution->transition->actions ?? collect();

        foreach ($actions as $action) {
            try {
                $result = $this->blueprintEngine->executeAction($action, $execution);
                $results[$action->id] = [
                    'status' => 'success',
                    'result' => $result,
                ];
            } catch (\Exception $e) {
                $results[$action->id] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                Log::warning('Blueprint transition action failed', [
                    'action_id' => $action->id,
                    'execution_id' => $execution->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Update the record's state after transition.
     */
    protected function updateRecordState(BlueprintTransitionExecution $execution): void
    {
        $blueprint = $execution->transition->blueprint;
        $toState = $execution->transition->toState;

        // Update or create the record state
        BlueprintRecordState::updateOrCreate(
            [
                'blueprint_id' => $blueprint->id,
                'record_id' => $execution->record_id,
            ],
            [
                'current_state_id' => $toState->id,
                'entered_at' => now(),
                'last_transition_id' => $execution->transition_id,
                'last_transition_at' => now(),
            ]
        );

        // Update the actual record field if blueprint is bound to a field
        if ($blueprint->field_id) {
            $this->updateRecordField($execution->record_id, $blueprint, $toState);
        }
    }

    /**
     * Update the record's field value to match the new state.
     */
    protected function updateRecordField(int $recordId, $blueprint, $toState): void
    {
        try {
            $record = \App\Models\ModuleRecord::find($recordId);
            if (!$record) {
                return;
            }

            $field = $blueprint->field;
            if (!$field) {
                return;
            }

            // Update the record data
            $data = $record->data ?? [];
            $data[$field->api_name] = $toState->name;
            $record->update(['data' => $data]);

        } catch (\Exception $e) {
            Log::warning('Failed to update record field after approval', [
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send completion notification.
     */
    protected function sendCompletionNotification(BlueprintTransitionExecution $execution): void
    {
        // TODO: Implement notification sending
        // This would notify the user who initiated the transition
        Log::debug('Would send completion notification', [
            'execution_id' => $execution->id,
            'executed_by' => $execution->executed_by,
        ]);
    }

    /**
     * Send rejection notification.
     */
    protected function sendRejectionNotification(BlueprintTransitionExecution $execution, BlueprintApprovalRequest $request): void
    {
        // TODO: Implement notification sending
        // This would notify the user who initiated the transition
        Log::debug('Would send rejection notification', [
            'execution_id' => $execution->id,
            'executed_by' => $execution->executed_by,
            'rejected_by' => $request->responded_by,
        ]);
    }
}
