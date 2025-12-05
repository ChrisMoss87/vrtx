<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    /**
     * Get all workflows.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Workflow::with(['module', 'steps']);

            // Filter by module if provided
            if ($request->has('module_id')) {
                $query->where('module_id', $request->input('module_id'));
            }

            // Filter by active status
            if ($request->has('active')) {
                $query->where('is_active', $request->boolean('active'));
            }

            // Filter by trigger type
            if ($request->has('trigger_type')) {
                $query->where('trigger_type', $request->input('trigger_type'));
            }

            $workflows = $query->orderBy('priority', 'desc')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'workflows' => $workflows,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workflows',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get available trigger types.
     */
    public function triggerTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'trigger_types' => Workflow::getTriggerTypes(),
        ]);
    }

    /**
     * Get available action types.
     */
    public function actionTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'action_types' => WorkflowStep::getActionTypes(),
        ]);
    }

    /**
     * Create a new workflow.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'module_id' => 'nullable|exists:modules,id',
                'is_active' => 'nullable|boolean',
                'priority' => 'nullable|integer',
                'trigger_type' => 'required|string|in:' . implode(',', array_keys(Workflow::getTriggerTypes())),
                'trigger_config' => 'nullable|array',
                'conditions' => 'nullable|array',
                'run_once_per_record' => 'nullable|boolean',
                'allow_manual_trigger' => 'nullable|boolean',
                'delay_seconds' => 'nullable|integer|min:0',
                'schedule_cron' => 'nullable|string|max:100',
                'steps' => 'nullable|array',
                'steps.*.name' => 'nullable|string|max:255',
                'steps.*.action_type' => 'required|string',
                'steps.*.action_config' => 'required|array',
                'steps.*.conditions' => 'nullable|array',
                'steps.*.continue_on_error' => 'nullable|boolean',
                'steps.*.retry_count' => 'nullable|integer|min:0|max:10',
                'steps.*.retry_delay_seconds' => 'nullable|integer|min:0',
            ]);

            $workflow = DB::transaction(function () use ($validated, $request) {
                $workflow = Workflow::create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'module_id' => $validated['module_id'] ?? null,
                    'is_active' => $validated['is_active'] ?? false,
                    'priority' => $validated['priority'] ?? 0,
                    'trigger_type' => $validated['trigger_type'],
                    'trigger_config' => $validated['trigger_config'] ?? [],
                    'conditions' => $validated['conditions'] ?? [],
                    'run_once_per_record' => $validated['run_once_per_record'] ?? false,
                    'allow_manual_trigger' => $validated['allow_manual_trigger'] ?? true,
                    'delay_seconds' => $validated['delay_seconds'] ?? 0,
                    'schedule_cron' => $validated['schedule_cron'] ?? null,
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]);

                // Create steps if provided
                if (!empty($validated['steps'])) {
                    foreach ($validated['steps'] as $index => $stepData) {
                        WorkflowStep::create([
                            'workflow_id' => $workflow->id,
                            'order' => $index,
                            'name' => $stepData['name'] ?? null,
                            'action_type' => $stepData['action_type'],
                            'action_config' => $stepData['action_config'],
                            'conditions' => $stepData['conditions'] ?? null,
                            'continue_on_error' => $stepData['continue_on_error'] ?? false,
                            'retry_count' => $stepData['retry_count'] ?? 0,
                            'retry_delay_seconds' => $stepData['retry_delay_seconds'] ?? 60,
                        ]);
                    }
                }

                return $workflow;
            });

            return response()->json([
                'success' => true,
                'message' => 'Workflow created successfully',
                'workflow' => $workflow->load(['module', 'steps']),
            ], Response::HTTP_CREATED);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create workflow',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a single workflow.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $workflow = Workflow::with(['module', 'steps', 'executions' => function ($query) {
                $query->latest()->limit(10);
            }])->find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'workflow' => $workflow,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch workflow',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a workflow.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $workflow = Workflow::find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'module_id' => 'nullable|exists:modules,id',
                'is_active' => 'nullable|boolean',
                'priority' => 'nullable|integer',
                'trigger_type' => 'sometimes|string|in:' . implode(',', array_keys(Workflow::getTriggerTypes())),
                'trigger_config' => 'nullable|array',
                'conditions' => 'nullable|array',
                'run_once_per_record' => 'nullable|boolean',
                'allow_manual_trigger' => 'nullable|boolean',
                'delay_seconds' => 'nullable|integer|min:0',
                'schedule_cron' => 'nullable|string|max:100',
                'steps' => 'nullable|array',
                'steps.*.id' => 'nullable|integer',
                'steps.*.name' => 'nullable|string|max:255',
                'steps.*.action_type' => 'required|string',
                'steps.*.action_config' => 'required|array',
                'steps.*.conditions' => 'nullable|array',
                'steps.*.continue_on_error' => 'nullable|boolean',
                'steps.*.retry_count' => 'nullable|integer|min:0|max:10',
                'steps.*.retry_delay_seconds' => 'nullable|integer|min:0',
            ]);

            DB::transaction(function () use ($workflow, $validated, $request) {
                // Update workflow fields
                $workflow->update([
                    'name' => $validated['name'] ?? $workflow->name,
                    'description' => $validated['description'] ?? $workflow->description,
                    'module_id' => array_key_exists('module_id', $validated) ? $validated['module_id'] : $workflow->module_id,
                    'is_active' => $validated['is_active'] ?? $workflow->is_active,
                    'priority' => $validated['priority'] ?? $workflow->priority,
                    'trigger_type' => $validated['trigger_type'] ?? $workflow->trigger_type,
                    'trigger_config' => $validated['trigger_config'] ?? $workflow->trigger_config,
                    'conditions' => $validated['conditions'] ?? $workflow->conditions,
                    'run_once_per_record' => $validated['run_once_per_record'] ?? $workflow->run_once_per_record,
                    'allow_manual_trigger' => $validated['allow_manual_trigger'] ?? $workflow->allow_manual_trigger,
                    'delay_seconds' => $validated['delay_seconds'] ?? $workflow->delay_seconds,
                    'schedule_cron' => $validated['schedule_cron'] ?? $workflow->schedule_cron,
                    'updated_by' => $request->user()?->id,
                ]);

                // Sync steps if provided
                if (isset($validated['steps'])) {
                    $this->syncSteps($workflow, $validated['steps']);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Workflow updated successfully',
                'workflow' => $workflow->fresh()->load(['module', 'steps']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workflow',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a workflow.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $workflow = Workflow::find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $workflow->delete();

            return response()->json([
                'success' => true,
                'message' => 'Workflow deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete workflow',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Toggle workflow active status.
     */
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $workflow = Workflow::find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $workflow->update(['is_active' => !$workflow->is_active]);

            return response()->json([
                'success' => true,
                'message' => $workflow->is_active ? 'Workflow activated' : 'Workflow deactivated',
                'workflow' => $workflow,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle workflow status',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Clone a workflow.
     */
    public function clone(int $id): JsonResponse
    {
        try {
            $workflow = Workflow::with('steps')->find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $newWorkflow = DB::transaction(function () use ($workflow) {
                // Clone workflow
                $newWorkflow = $workflow->replicate();
                $newWorkflow->name = $workflow->name . ' (Copy)';
                $newWorkflow->is_active = false;
                $newWorkflow->execution_count = 0;
                $newWorkflow->success_count = 0;
                $newWorkflow->failure_count = 0;
                $newWorkflow->last_run_at = null;
                $newWorkflow->next_run_at = null;
                $newWorkflow->save();

                // Clone steps
                foreach ($workflow->steps as $step) {
                    $newStep = $step->replicate();
                    $newStep->workflow_id = $newWorkflow->id;
                    $newStep->save();
                }

                return $newWorkflow;
            });

            return response()->json([
                'success' => true,
                'message' => 'Workflow cloned successfully',
                'workflow' => $newWorkflow->load(['module', 'steps']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clone workflow',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Manually trigger a workflow.
     */
    public function trigger(Request $request, int $id): JsonResponse
    {
        try {
            $workflow = Workflow::find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$workflow->allow_manual_trigger) {
                return response()->json([
                    'success' => false,
                    'message' => 'This workflow does not allow manual triggers',
                ], Response::HTTP_FORBIDDEN);
            }

            $validated = $request->validate([
                'record_id' => 'nullable|integer',
                'context_data' => 'nullable|array',
            ]);

            // Create execution record
            $execution = $workflow->executions()->create([
                'trigger_type' => 'manual',
                'trigger_record_id' => $validated['record_id'] ?? null,
                'trigger_record_type' => $validated['record_id'] ? 'App\\Models\\ModuleRecord' : null,
                'status' => 'pending',
                'context_data' => $validated['context_data'] ?? [],
                'triggered_by' => $request->user()?->id,
            ]);

            // TODO: Dispatch job to execute workflow
            // dispatch(new ExecuteWorkflowJob($execution));

            return response()->json([
                'success' => true,
                'message' => 'Workflow execution triggered',
                'execution' => $execution,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to trigger workflow',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get workflow execution history.
     */
    public function executions(Request $request, int $id): JsonResponse
    {
        try {
            $workflow = Workflow::find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $query = $workflow->executions()->with(['stepLogs.step']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Pagination
            $perPage = $request->input('per_page', 20);
            $executions = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'executions' => $executions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch executions',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a single execution with details.
     */
    public function showExecution(int $id, int $executionId): JsonResponse
    {
        try {
            $workflow = Workflow::find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $execution = $workflow->executions()
                ->with(['stepLogs.step', 'triggeredBy'])
                ->find($executionId);

            if (!$execution) {
                return response()->json([
                    'success' => false,
                    'message' => 'Execution not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'execution' => $execution,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch execution',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reorder workflow steps.
     */
    public function reorderSteps(Request $request, int $id): JsonResponse
    {
        try {
            $workflow = Workflow::find($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'steps' => 'required|array',
                'steps.*' => 'required|integer|exists:workflow_steps,id',
            ]);

            DB::transaction(function () use ($validated, $workflow) {
                foreach ($validated['steps'] as $order => $stepId) {
                    WorkflowStep::where('id', $stepId)
                        ->where('workflow_id', $workflow->id)
                        ->update(['order' => $order]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Steps reordered successfully',
                'workflow' => $workflow->fresh()->load(['steps']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder steps',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sync steps for a workflow (create, update, delete).
     */
    private function syncSteps(Workflow $workflow, array $stepsData): void
    {
        $existingStepIds = $workflow->steps->pluck('id')->toArray();
        $incomingStepIds = [];

        foreach ($stepsData as $index => $stepData) {
            $stepPayload = [
                'workflow_id' => $workflow->id,
                'order' => $index,
                'name' => $stepData['name'] ?? null,
                'action_type' => $stepData['action_type'],
                'action_config' => $stepData['action_config'],
                'conditions' => $stepData['conditions'] ?? null,
                'continue_on_error' => $stepData['continue_on_error'] ?? false,
                'retry_count' => $stepData['retry_count'] ?? 0,
                'retry_delay_seconds' => $stepData['retry_delay_seconds'] ?? 60,
            ];

            if (!empty($stepData['id'])) {
                // Update existing step
                $step = WorkflowStep::find($stepData['id']);
                if ($step && $step->workflow_id === $workflow->id) {
                    $step->update($stepPayload);
                    $incomingStepIds[] = $step->id;
                }
            } else {
                // Create new step
                $step = WorkflowStep::create($stepPayload);
                $incomingStepIds[] = $step->id;
            }
        }

        // Delete steps that were removed
        $stepsToDelete = array_diff($existingStepIds, $incomingStepIds);
        if (!empty($stepsToDelete)) {
            WorkflowStep::whereIn('id', $stepsToDelete)->delete();
        }
    }
}
