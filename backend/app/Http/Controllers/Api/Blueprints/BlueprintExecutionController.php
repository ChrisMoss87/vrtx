<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Blueprints;

use App\Application\Services\Blueprint\BlueprintApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Blueprint\ApprovalService;
use App\Services\Blueprint\BlueprintEngine;
use App\Services\Blueprint\RequirementService;
use App\Services\Blueprint\SLAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BlueprintExecutionController extends Controller
{
    public function __construct(
        protected BlueprintEngine $engine,
        protected RequirementService $requirementService,
        protected ApprovalService $approvalService,
        protected SLAService $slaService,
        protected BlueprintApplicationService $blueprintApplicationService
    ) {}

    /**
     * Get the current state and available transitions for a record.
     */
    public function getRecordState(Request $request, int $recordId): JsonResponse
    {
        try {
            $blueprintId = $request->input('blueprint_id');
            $moduleId = $request->input('module_id');
            $fieldId = $request->input('field_id');

            // Find the blueprint
            $blueprint = null;
            if ($blueprintId) {
                $blueprint = DB::table('blueprints')->where('id', $blueprintId)->first();
            } elseif ($moduleId && $fieldId) {
                $blueprint = $this->engine->getBlueprintForModuleField($moduleId, $fieldId);
            }

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Get record data (would typically fetch from module table)
            $recordData = $request->input('record_data', []);

            // Get current state
            $recordState = $this->engine->getRecordState($blueprint->id, $recordId);

            // Get available transitions
            $availableTransitions = $this->engine->getAvailableTransitions($blueprint->id, $recordId, $recordData);

            // Get SLA status
            $slaStatus = $this->slaService->getSLAStatus($blueprint->id, $recordId);

            return response()->json([
                'success' => true,
                'blueprint' => [
                    'id' => $blueprint->id,
                    'name' => $blueprint->name,
                    'is_active' => $blueprint->is_active,
                ],
                'current_state' => $recordState ? [
                    'id' => $recordState->current_state_id,
                    'name' => $recordState->currentState->name,
                    'color' => $recordState->currentState->color,
                    'is_terminal' => $recordState->currentState->is_terminal,
                    'entered_at' => $recordState->state_entered_at?->toIso8601String(),
                ] : null,
                'available_transitions' => $availableTransitions->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'button_label' => $t->getButtonLabel(),
                        'to_state' => [
                            'id' => $t->toState->id,
                            'name' => $t->toState->name,
                            'color' => $t->toState->color,
                        ],
                        'has_requirements' => $t->hasRequirements(),
                        'requires_approval' => $t->requiresApproval(),
                    ];
                }),
                'sla_status' => $slaStatus,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get record state',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Start a transition.
     */
    public function startTransition(Request $request, int $recordId, int $transitionId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'record_data' => 'nullable|array',
            ]);

            $userId = $request->user()->id;
            $recordData = $validated['record_data'] ?? [];

            $execution = $this->engine->startTransition($recordId, $transitionId, $userId, $recordData);

            // Get requirements for the transition
            $requirements = [];
            if ($execution->isPendingRequirements()) {
                $requirements = $this->requirementService->formatRequirements(
                    $this->requirementService->getRequirements($execution->transition)
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Transition started',
                'execution' => [
                    'id' => $execution->id,
                    'status' => $execution->status,
                    'transition' => [
                        'id' => $execution->transition->id,
                        'name' => $execution->transition->name,
                    ],
                    'to_state' => [
                        'id' => $execution->toState->id,
                        'name' => $execution->toState->name,
                    ],
                ],
                'requirements' => $requirements,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start transition',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Submit requirements for an execution.
     */
    public function submitRequirements(Request $request, int $executionId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'fields' => 'nullable|array',
                'attachments' => 'nullable|array',
                'note' => 'nullable|string',
                'checklist' => 'nullable|array',
            ]);

            $execution = $this->engine->submitRequirements($executionId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Requirements submitted',
                'execution' => [
                    'id' => $execution->id,
                    'status' => $execution->status,
                ],
                'next_step' => match ($execution->status) {
                    BlueprintTransitionExecution::STATUS_PENDING_APPROVAL => 'awaiting_approval',
                    BlueprintTransitionExecution::STATUS_PENDING => 'ready_to_complete',
                    default => $execution->status,
                },
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit requirements',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Complete an execution.
     */
    public function completeExecution(int $executionId): JsonResponse
    {
        try {
            $execution = $this->engine->completeTransition($executionId);

            return response()->json([
                'success' => true,
                'message' => 'Transition completed',
                'execution' => [
                    'id' => $execution->id,
                    'status' => $execution->status,
                    'completed_at' => $execution->completed_at?->toIso8601String(),
                ],
                'new_state' => [
                    'id' => $execution->toState->id,
                    'name' => $execution->toState->name,
                    'color' => $execution->toState->color,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete transition',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancel an execution.
     */
    public function cancelExecution(int $executionId): JsonResponse
    {
        try {
            $this->engine->cancelTransition($executionId);

            return response()->json([
                'success' => true,
                'message' => 'Transition cancelled',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel transition',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get transition history for a record.
     */
    public function getTransitionHistory(Request $request, int $recordId): JsonResponse
    {
        try {
            $blueprintId = $request->input('blueprint_id');

            if (!$blueprintId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint ID is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $history = $this->engine->getTransitionHistory($blueprintId, $recordId);

            return response()->json([
                'success' => true,
                'history' => $history->map(function ($execution) {
                    return [
                        'id' => $execution->id,
                        'transition' => [
                            'id' => $execution->transition->id,
                            'name' => $execution->transition->name,
                        ],
                        'from_state' => $execution->fromState ? [
                            'id' => $execution->fromState->id,
                            'name' => $execution->fromState->name,
                        ] : null,
                        'to_state' => [
                            'id' => $execution->toState->id,
                            'name' => $execution->toState->name,
                        ],
                        'executed_by' => $execution->executedBy ? [
                            'id' => $execution->executedBy->id,
                            'name' => $execution->executedBy->name,
                        ] : null,
                        'status' => $execution->status,
                        'started_at' => $execution->started_at?->toIso8601String(),
                        'completed_at' => $execution->completed_at?->toIso8601String(),
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get transition history',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get SLA status for a record.
     */
    public function getSLAStatus(Request $request, int $recordId): JsonResponse
    {
        try {
            $blueprintId = $request->input('blueprint_id');

            if (!$blueprintId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint ID is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $slaStatus = $this->slaService->getSLAStatus($blueprintId, $recordId);

            return response()->json([
                'success' => true,
                'sla_status' => $slaStatus,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get SLA status',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Approve a pending approval request.
     */
    public function approve(Request $request, int $requestId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'comments' => 'nullable|string',
            ]);

            $userId = $request->user()->id;
            $approvalRequest = $this->approvalService->approve(
                $requestId,
                $userId,
                $validated['comments'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Approval request approved',
                'request' => [
                    'id' => $approvalRequest->id,
                    'status' => $approvalRequest->status,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve request',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reject a pending approval request.
     */
    public function reject(Request $request, int $requestId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'comments' => 'nullable|string',
            ]);

            $userId = $request->user()->id;
            $approvalRequest = $this->approvalService->reject(
                $requestId,
                $userId,
                $validated['comments'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Approval request rejected',
                'request' => [
                    'id' => $approvalRequest->id,
                    'status' => $approvalRequest->status,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject request',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get pending approvals for the current user.
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $pending = $this->approvalService->getPendingApprovalsForUser($userId);

            return response()->json([
                'success' => true,
                'pending_approvals' => $pending->map(function ($req) {
                    return [
                        'id' => $req->id,
                        'record_id' => $req->record_id,
                        'execution' => [
                            'id' => $req->execution->id,
                            'transition' => [
                                'id' => $req->execution->transition->id,
                                'name' => $req->execution->transition->name,
                            ],
                            'blueprint' => [
                                'id' => $req->execution->transition->blueprint->id,
                                'name' => $req->execution->transition->blueprint->name,
                            ],
                            'module' => [
                                'id' => $req->execution->transition->blueprint->module->id,
                                'name' => $req->execution->transition->blueprint->module->name,
                            ],
                        ],
                        'requested_by' => $req->requestedBy ? [
                            'id' => $req->requestedBy->id,
                            'name' => $req->requestedBy->name,
                        ] : null,
                        'created_at' => $req->created_at->toIso8601String(),
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get pending approvals',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
