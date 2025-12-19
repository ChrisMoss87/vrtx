<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workflows;

use App\Application\Services\Workflow\WorkflowApplicationService;
use App\Domain\Workflow\DTOs\CreateWorkflowDTO;
use App\Domain\Workflow\DTOs\CreateWorkflowStepDTO;
use App\Domain\Workflow\DTOs\UpdateWorkflowDTO;
use App\Domain\Workflow\ValueObjects\ActionType;
use App\Domain\Workflow\ValueObjects\TriggerType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowApplicationService $workflowService,
    ) {}

    /**
     * Get all workflows.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $workflows = $this->workflowService->getAllWorkflows();

            // Apply filters if provided (filtering DTOs)
            if ($request->has('module_id')) {
                $moduleId = (int) $request->input('module_id');
                $workflows = array_filter($workflows, fn($w) => $w->moduleId === $moduleId);
            }

            if ($request->has('active')) {
                $active = $request->boolean('active');
                $workflows = array_filter($workflows, fn($w) => $w->isActive === $active);
            }

            if ($request->has('trigger_type')) {
                $triggerType = $request->input('trigger_type');
                $workflows = array_filter($workflows, fn($w) => $w->triggerType === $triggerType);
            }

            return response()->json([
                'success' => true,
                'workflows' => array_values($workflows),
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
        $types = [];
        foreach (TriggerType::cases() as $type) {
            $types[$type->value] = $type->label();
        }

        return response()->json([
            'success' => true,
            'trigger_types' => $types,
        ]);
    }

    /**
     * Get available action types.
     */
    public function actionTypes(): JsonResponse
    {
        $types = [];
        foreach (ActionType::cases() as $type) {
            $types[$type->value] = $type->label();
        }

        return response()->json([
            'success' => true,
            'action_types' => $types,
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
                'trigger_type' => 'required|string|in:' . implode(',', array_column(TriggerType::cases(), 'value')),
                'trigger_config' => 'nullable|array',
                'trigger_timing' => 'nullable|string',
                'watched_fields' => 'nullable|array',
                'conditions' => 'nullable|array',
                'run_once_per_record' => 'nullable|boolean',
                'allow_manual_trigger' => 'nullable|boolean',
                'stop_on_first_match' => 'nullable|boolean',
                'max_executions_per_day' => 'nullable|integer|min:1',
                'delay_seconds' => 'nullable|integer|min:0',
                'schedule_cron' => 'nullable|string|max:100',
                'steps' => 'nullable|array',
                'steps.*.name' => 'nullable|string|max:255',
                'steps.*.action_type' => 'required|string',
                'steps.*.action_config' => 'required|array',
                'steps.*.conditions' => 'nullable|array',
                'steps.*.branch_id' => 'nullable|string',
                'steps.*.is_parallel' => 'nullable|boolean',
                'steps.*.continue_on_error' => 'nullable|boolean',
                'steps.*.retry_count' => 'nullable|integer|min:0|max:10',
                'steps.*.retry_delay_seconds' => 'nullable|integer|min:0',
            ]);

            // Build step DTOs
            $steps = [];
            foreach (($validated['steps'] ?? []) as $index => $stepData) {
                $steps[] = CreateWorkflowStepDTO::fromArray([
                    'order' => $index,
                    'name' => $stepData['name'] ?? null,
                    'action_type' => $stepData['action_type'],
                    'action_config' => $stepData['action_config'],
                    'conditions' => $stepData['conditions'] ?? [],
                    'branch_id' => $stepData['branch_id'] ?? null,
                    'is_parallel' => $stepData['is_parallel'] ?? false,
                    'continue_on_error' => $stepData['continue_on_error'] ?? false,
                    'retry_count' => $stepData['retry_count'] ?? 0,
                    'retry_delay_seconds' => $stepData['retry_delay_seconds'] ?? 60,
                ]);
            }

            $dto = CreateWorkflowDTO::fromArray([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'module_id' => $validated['module_id'] ?? null,
                'trigger_type' => $validated['trigger_type'],
                'trigger_config' => $validated['trigger_config'] ?? [],
                'trigger_timing' => $validated['trigger_timing'] ?? 'all',
                'watched_fields' => $validated['watched_fields'] ?? [],
                'conditions' => $validated['conditions'] ?? [],
                'priority' => $validated['priority'] ?? 0,
                'stop_on_first_match' => $validated['stop_on_first_match'] ?? false,
                'max_executions_per_day' => $validated['max_executions_per_day'] ?? null,
                'run_once_per_record' => $validated['run_once_per_record'] ?? false,
                'allow_manual_trigger' => $validated['allow_manual_trigger'] ?? true,
                'delay_seconds' => $validated['delay_seconds'] ?? 0,
                'schedule_cron' => $validated['schedule_cron'] ?? null,
                'created_by' => $request->user()?->id,
                'steps' => $steps,
            ]);

            $workflow = $this->workflowService->createWorkflow($dto);

            return response()->json([
                'success' => true,
                'message' => 'Workflow created successfully',
                'workflow' => $workflow,
            ], Response::HTTP_CREATED);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'error' => $e->getMessage(),
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
            $workflow = $this->workflowService->getWorkflow($id);

            if (!$workflow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Add statistics
            $stats = $this->workflowService->getWorkflowStatistics($id, 30);

            return response()->json([
                'success' => true,
                'workflow' => $workflow,
                'statistics' => $stats,
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
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'module_id' => 'nullable|exists:modules,id',
                'is_active' => 'nullable|boolean',
                'priority' => 'nullable|integer',
                'trigger_type' => 'sometimes|string|in:' . implode(',', array_column(TriggerType::cases(), 'value')),
                'trigger_config' => 'nullable|array',
                'trigger_timing' => 'nullable|string',
                'watched_fields' => 'nullable|array',
                'conditions' => 'nullable|array',
                'run_once_per_record' => 'nullable|boolean',
                'allow_manual_trigger' => 'nullable|boolean',
                'stop_on_first_match' => 'nullable|boolean',
                'max_executions_per_day' => 'nullable|integer|min:1',
                'delay_seconds' => 'nullable|integer|min:0',
                'schedule_cron' => 'nullable|string|max:100',
                'steps' => 'nullable|array',
                'steps.*.id' => 'nullable|integer',
                'steps.*.name' => 'nullable|string|max:255',
                'steps.*.action_type' => 'required|string',
                'steps.*.action_config' => 'required|array',
                'steps.*.conditions' => 'nullable|array',
                'steps.*.branch_id' => 'nullable|string',
                'steps.*.is_parallel' => 'nullable|boolean',
                'steps.*.continue_on_error' => 'nullable|boolean',
                'steps.*.retry_count' => 'nullable|integer|min:0|max:10',
                'steps.*.retry_delay_seconds' => 'nullable|integer|min:0',
            ]);

            // Get existing workflow for defaults
            $existing = $this->workflowService->getWorkflow($id);
            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Build step DTOs if provided
            $steps = null;
            if (isset($validated['steps'])) {
                $steps = [];
                foreach ($validated['steps'] as $index => $stepData) {
                    $steps[] = CreateWorkflowStepDTO::fromArray([
                        'order' => $index,
                        'name' => $stepData['name'] ?? null,
                        'action_type' => $stepData['action_type'],
                        'action_config' => $stepData['action_config'],
                        'conditions' => $stepData['conditions'] ?? [],
                        'branch_id' => $stepData['branch_id'] ?? null,
                        'is_parallel' => $stepData['is_parallel'] ?? false,
                        'continue_on_error' => $stepData['continue_on_error'] ?? false,
                        'retry_count' => $stepData['retry_count'] ?? 0,
                        'retry_delay_seconds' => $stepData['retry_delay_seconds'] ?? 60,
                    ]);
                }
            }

            $dto = UpdateWorkflowDTO::fromArray([
                'id' => $id,
                'name' => $validated['name'] ?? $existing->name,
                'description' => $validated['description'] ?? $existing->description,
                'trigger_type' => $validated['trigger_type'] ?? $existing->triggerType,
                'trigger_config' => $validated['trigger_config'] ?? [],
                'trigger_timing' => $validated['trigger_timing'] ?? 'all',
                'watched_fields' => $validated['watched_fields'] ?? [],
                'conditions' => $validated['conditions'] ?? [],
                'is_active' => $validated['is_active'] ?? null,
                'priority' => $validated['priority'] ?? $existing->priority,
                'stop_on_first_match' => $validated['stop_on_first_match'] ?? false,
                'max_executions_per_day' => $validated['max_executions_per_day'] ?? null,
                'run_once_per_record' => $validated['run_once_per_record'] ?? false,
                'allow_manual_trigger' => $validated['allow_manual_trigger'] ?? true,
                'delay_seconds' => $validated['delay_seconds'] ?? 0,
                'schedule_cron' => $validated['schedule_cron'] ?? null,
                'updated_by' => $request->user()?->id,
                'steps' => $steps,
            ]);

            $workflow = $this->workflowService->updateWorkflow($dto);

            return response()->json([
                'success' => true,
                'message' => 'Workflow updated successfully',
                'workflow' => $workflow,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
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
            $deleted = $this->workflowService->deleteWorkflow($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

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
            $existing = $this->workflowService->getWorkflow($id);

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            if ($existing->isActive) {
                $workflow = $this->workflowService->deactivateWorkflow($id);
                $message = 'Workflow deactivated';
            } else {
                $workflow = $this->workflowService->activateWorkflow($id);
                $message = 'Workflow activated';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'workflow' => $workflow,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
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
            $existing = $this->workflowService->getWorkflow($id);

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Create steps DTOs from existing
            $steps = [];
            foreach ($existing->steps as $index => $step) {
                $steps[] = CreateWorkflowStepDTO::fromArray([
                    'order' => $index,
                    'name' => $step['name'] ?? null,
                    'action_type' => $step['actionType'],
                    'action_config' => $step['actionConfig'] ?? [],
                    'conditions' => $step['conditions'] ?? [],
                    'branch_id' => $step['branchId'] ?? null,
                    'is_parallel' => $step['isParallel'] ?? false,
                    'continue_on_error' => $step['continueOnError'] ?? false,
                    'retry_count' => $step['retryCount'] ?? 0,
                    'retry_delay_seconds' => $step['retryDelaySeconds'] ?? 60,
                ]);
            }

            $dto = CreateWorkflowDTO::fromArray([
                'name' => $existing->name . ' (Copy)',
                'description' => $existing->description,
                'module_id' => $existing->moduleId,
                'trigger_type' => $existing->triggerType,
                'trigger_config' => [],
                'trigger_timing' => 'all',
                'watched_fields' => [],
                'conditions' => [],
                'priority' => $existing->priority,
                'stop_on_first_match' => false,
                'max_executions_per_day' => null,
                'run_once_per_record' => false,
                'allow_manual_trigger' => true,
                'delay_seconds' => 0,
                'schedule_cron' => null,
                'created_by' => null,
                'steps' => $steps,
            ]);

            $workflow = $this->workflowService->createWorkflow($dto);

            return response()->json([
                'success' => true,
                'message' => 'Workflow cloned successfully',
                'workflow' => $workflow,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
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
            $existing = $this->workflowService->getWorkflow($id);

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$existing->allowManualTrigger) {
                return response()->json([
                    'success' => false,
                    'message' => 'This workflow does not allow manual triggers',
                ], Response::HTTP_FORBIDDEN);
            }

            $validated = $request->validate([
                'record_id' => 'nullable|integer',
                'context_data' => 'nullable|array',
            ]);

            $execution = $this->workflowService->createExecution(
                workflowId: $id,
                triggerType: 'manual',
                recordId: $validated['record_id'] ?? null,
                recordType: isset($validated['record_id']) ? 'ModuleRecord' : null,
                contextData: $validated['context_data'] ?? [],
                triggeredByUserId: $request->user()?->id,
            );

            return response()->json([
                'success' => true,
                'message' => 'Workflow execution triggered',
                'execution_id' => $execution->getId(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
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
            $existing = $this->workflowService->getWorkflow($id);

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // For now, return statistics - full execution history would need repository method
            $stats = $this->workflowService->getWorkflowStatistics($id, 30);

            return response()->json([
                'success' => true,
                'statistics' => $stats,
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
     * Reorder workflow steps.
     */
    public function reorderSteps(Request $request, int $id): JsonResponse
    {
        try {
            $existing = $this->workflowService->getWorkflow($id);

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'step_order' => 'required|array',
                'step_order.*' => 'required|integer',
            ]);

            // Reorder steps based on the new order
            $stepOrder = $validated['step_order'];
            $reorderedSteps = [];

            foreach ($stepOrder as $newOrder => $stepId) {
                foreach ($existing->steps as $step) {
                    if (($step['id'] ?? null) === $stepId) {
                        $reorderedSteps[] = CreateWorkflowStepDTO::fromArray([
                            'order' => $newOrder,
                            'name' => $step['name'] ?? null,
                            'action_type' => $step['actionType'],
                            'action_config' => $step['actionConfig'] ?? [],
                            'conditions' => $step['conditions'] ?? [],
                            'branch_id' => $step['branchId'] ?? null,
                            'is_parallel' => $step['isParallel'] ?? false,
                            'continue_on_error' => $step['continueOnError'] ?? false,
                            'retry_count' => $step['retryCount'] ?? 0,
                            'retry_delay_seconds' => $step['retryDelaySeconds'] ?? 60,
                        ]);
                        break;
                    }
                }
            }

            $dto = UpdateWorkflowDTO::fromArray([
                'id' => $id,
                'name' => $existing->name,
                'description' => $existing->description,
                'trigger_type' => $existing->triggerType,
                'trigger_config' => [],
                'trigger_timing' => 'all',
                'watched_fields' => [],
                'conditions' => [],
                'priority' => $existing->priority,
                'stop_on_first_match' => false,
                'max_executions_per_day' => null,
                'run_once_per_record' => false,
                'allow_manual_trigger' => true,
                'delay_seconds' => 0,
                'schedule_cron' => null,
                'updated_by' => $request->user()?->id,
                'steps' => $reorderedSteps,
            ]);

            $workflow = $this->workflowService->updateWorkflow($dto);

            return response()->json([
                'success' => true,
                'message' => 'Steps reordered successfully',
                'workflow' => $workflow,
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
     * Get version history for a workflow.
     */
    public function versions(int $id): JsonResponse
    {
        try {
            $existing = $this->workflowService->getWorkflow($id);

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $versioningService = app(\App\Services\Workflow\WorkflowVersioningService::class);
            $versions = $versioningService->getVersionHistory($id);

            return response()->json([
                'success' => true,
                'versions' => $versions,
                'current_version' => $existing->currentVersion ?? 1,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch versions',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific version.
     */
    public function showVersion(int $id, int $versionId): JsonResponse
    {
        try {
            $existing = $this->workflowService->getWorkflow($id);

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $versioningService = app(\App\Services\Workflow\WorkflowVersioningService::class);
            $version = $versioningService->getVersionDetails($versionId);

            if (!$version || $version['workflow_id'] !== $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Version not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'version' => $version,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch version',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Rollback to a specific version.
     */
    public function rollback(Request $request, int $id, int $versionId): JsonResponse
    {
        try {
            $existing = $this->workflowService->getWorkflow($id);

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $versioningService = app(\App\Services\Workflow\WorkflowVersioningService::class);
            $version = $versioningService->getVersion($versionId);

            if (!$version || $version->workflow_id !== $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Version not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $workflow = $versioningService->rollbackToVersion($versionId, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => "Workflow restored to version {$version->version_number}",
                'workflow' => $this->workflowService->getWorkflow($workflow->id),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback workflow',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Compare two versions.
     */
    public function compareVersions(int $id, int $versionId1, int $versionId2): JsonResponse
    {
        try {
            $existing = $this->workflowService->getWorkflow($id);

            if (!$existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workflow not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $versioningService = app(\App\Services\Workflow\WorkflowVersioningService::class);

            $comparison = $versioningService->compareVersions($versionId1, $versionId2);

            return response()->json([
                'success' => true,
                'comparison' => $comparison,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to compare versions',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
