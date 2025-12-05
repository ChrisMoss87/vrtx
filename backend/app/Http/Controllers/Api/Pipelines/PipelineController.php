<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pipelines;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleRecord;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\StageHistory;
use App\Services\PipelineFieldSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PipelineController extends Controller
{
    public function __construct(
        private PipelineFieldSyncService $fieldSyncService
    ) {}

    /**
     * Get all pipelines.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Pipeline::with(['stages', 'module']);

            // Filter by module if provided
            if ($request->has('module_id')) {
                $query->where('module_id', $request->input('module_id'));
            }

            // Filter by active status
            if ($request->has('active')) {
                $query->where('is_active', $request->boolean('active'));
            }

            $pipelines = $query->get();

            return response()->json([
                'success' => true,
                'pipelines' => $pipelines,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pipelines',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get pipelines for a specific module (by API name).
     */
    public function forModule(string $moduleApiName): JsonResponse
    {
        try {
            $module = Module::where('api_name', $moduleApiName)->first();

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $pipelines = Pipeline::with(['stages'])
                ->where('module_id', $module->id)
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'pipelines' => $pipelines,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pipelines for module',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new pipeline.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'module_id' => 'required|exists:modules,id',
                'stage_field_api_name' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
                'settings' => 'nullable|array',
                'stages' => 'nullable|array',
                'stages.*.name' => 'required|string|max:255',
                'stages.*.color' => 'nullable|string|max:20',
                'stages.*.probability' => 'nullable|integer|min:0|max:100',
                'stages.*.is_won_stage' => 'nullable|boolean',
                'stages.*.is_lost_stage' => 'nullable|boolean',
                'stages.*.settings' => 'nullable|array',
            ]);

            $pipeline = DB::transaction(function () use ($validated, $request) {
                $pipeline = Pipeline::create([
                    'name' => $validated['name'],
                    'module_id' => $validated['module_id'],
                    'stage_field_api_name' => $validated['stage_field_api_name'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                    'settings' => $validated['settings'] ?? [],
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]);

                // Create stages if provided
                if (!empty($validated['stages'])) {
                    foreach ($validated['stages'] as $index => $stageData) {
                        Stage::create([
                            'pipeline_id' => $pipeline->id,
                            'name' => $stageData['name'],
                            'color' => $stageData['color'] ?? '#6b7280',
                            'probability' => $stageData['probability'] ?? 0,
                            'display_order' => $index,
                            'is_won_stage' => $stageData['is_won_stage'] ?? false,
                            'is_lost_stage' => $stageData['is_lost_stage'] ?? false,
                            'settings' => $stageData['settings'] ?? [],
                        ]);
                    }
                }

                return $pipeline;
            });

            // Sync field options with stages
            $this->fieldSyncService->syncFieldOptionsFromStages($pipeline);

            return response()->json([
                'success' => true,
                'message' => 'Pipeline created successfully',
                'pipeline' => $pipeline->load(['stages', 'module']),
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
                'message' => 'Failed to create pipeline',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a single pipeline.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $pipeline = Pipeline::with(['stages', 'module'])->find($id);

            if (!$pipeline) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pipeline not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'pipeline' => $pipeline,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pipeline',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a pipeline.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $pipeline = Pipeline::find($id);

            if (!$pipeline) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pipeline not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'stage_field_api_name' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
                'settings' => 'nullable|array',
                'stages' => 'nullable|array',
                'stages.*.id' => 'nullable|integer',
                'stages.*.name' => 'required|string|max:255',
                'stages.*.color' => 'nullable|string|max:20',
                'stages.*.probability' => 'nullable|integer|min:0|max:100',
                'stages.*.display_order' => 'nullable|integer',
                'stages.*.is_won_stage' => 'nullable|boolean',
                'stages.*.is_lost_stage' => 'nullable|boolean',
                'stages.*.settings' => 'nullable|array',
            ]);

            DB::transaction(function () use ($pipeline, $validated, $request) {
                // Update pipeline
                $pipeline->update([
                    'name' => $validated['name'] ?? $pipeline->name,
                    'stage_field_api_name' => $validated['stage_field_api_name'] ?? $pipeline->stage_field_api_name,
                    'is_active' => $validated['is_active'] ?? $pipeline->is_active,
                    'settings' => $validated['settings'] ?? $pipeline->settings,
                    'updated_by' => $request->user()?->id,
                ]);

                // Sync stages if provided
                if (isset($validated['stages'])) {
                    $this->syncStages($pipeline, $validated['stages']);
                }
            });

            // Sync field options with stages
            $this->fieldSyncService->syncFieldOptionsFromStages($pipeline->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Pipeline updated successfully',
                'pipeline' => $pipeline->fresh()->load(['stages', 'module']),
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
                'message' => 'Failed to update pipeline',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a pipeline.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $pipeline = Pipeline::find($id);

            if (!$pipeline) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pipeline not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $pipeline->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pipeline deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete pipeline',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get kanban board data for a pipeline.
     */
    public function kanbanData(int $id, Request $request): JsonResponse
    {
        try {
            $pipeline = Pipeline::with(['stages', 'module.fields'])->find($id);

            if (!$pipeline) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pipeline not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $stageFieldName = $pipeline->stage_field_api_name ?? 'stage_id';
            $valueFieldName = $request->input('value_field', 'value');

            // Build query for records
            $recordsQuery = ModuleRecord::where('module_id', $pipeline->module_id);

            // Apply filters if provided
            if ($request->has('filters')) {
                foreach ($request->input('filters') as $field => $value) {
                    if (!empty($value)) {
                        $recordsQuery->whereRaw("data->>? = ?", [$field, $value]);
                    }
                }
            }

            // Apply search if provided
            if ($request->has('search')) {
                $searchTerm = $request->input('search');
                $searchableFields = $pipeline->module->fields
                    ->where('is_searchable', true)
                    ->pluck('api_name')
                    ->toArray();

                $recordsQuery->search($searchTerm, $searchableFields);
            }

            $records = $recordsQuery->get();

            // Group records by stage
            $columns = [];
            foreach ($pipeline->stages as $stage) {
                $stageRecords = $records->filter(function ($record) use ($stageFieldName, $stage) {
                    $stageValue = $record->data[$stageFieldName] ?? null;
                    return $stageValue == $stage->id || $stageValue == (string) $stage->id;
                });

                $totalValue = 0;
                if ($valueFieldName) {
                    $totalValue = $stageRecords->sum(function ($record) use ($valueFieldName) {
                        return (float) ($record->data[$valueFieldName] ?? 0);
                    });
                }

                $columns[] = [
                    'stage' => $stage,
                    'records' => $stageRecords->values(),
                    'count' => $stageRecords->count(),
                    'totalValue' => $totalValue,
                    'weightedValue' => $totalValue * ($stage->probability / 100),
                ];
            }

            // Calculate pipeline totals
            $totals = [
                'totalRecords' => $records->count(),
                'totalValue' => array_sum(array_column($columns, 'totalValue')),
                'weightedValue' => array_sum(array_column($columns, 'weightedValue')),
            ];

            return response()->json([
                'success' => true,
                'pipeline' => $pipeline,
                'columns' => $columns,
                'totals' => $totals,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch kanban data',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Move a record to a different stage.
     */
    public function moveRecord(Request $request, int $id): JsonResponse
    {
        try {
            $pipeline = Pipeline::find($id);

            if (!$pipeline) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pipeline not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'record_id' => 'required|exists:module_records,id',
                'stage_id' => 'required|exists:stages,id',
                'reason' => 'nullable|string|max:500',
            ]);

            $record = ModuleRecord::find($validated['record_id']);
            $toStage = Stage::find($validated['stage_id']);

            // Verify stage belongs to this pipeline
            if ($toStage->pipeline_id !== $pipeline->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stage does not belong to this pipeline',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Verify record belongs to this pipeline's module
            if ($record->module_id !== $pipeline->module_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record does not belong to this pipeline\'s module',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $stageFieldName = $pipeline->stage_field_api_name ?? 'stage_id';
            $fromStageId = $record->data[$stageFieldName] ?? null;

            DB::transaction(function () use ($record, $stageFieldName, $toStage, $pipeline, $fromStageId, $validated, $request) {
                // Update record's stage
                $data = $record->data;
                $data[$stageFieldName] = (string) $toStage->id;
                $record->data = $data;
                $record->updated_by = $request->user()?->id;
                $record->save();

                // Record stage history
                StageHistory::recordTransition(
                    recordId: $record->id,
                    pipelineId: $pipeline->id,
                    fromStageId: $fromStageId ? (int) $fromStageId : null,
                    toStageId: $toStage->id,
                    userId: $request->user()?->id ?? 0,
                    reason: $validated['reason'] ?? null
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'Record moved successfully',
                'record' => $record->fresh(),
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
                'message' => 'Failed to move record',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get stage history for a record.
     */
    public function recordHistory(int $id, int $recordId): JsonResponse
    {
        try {
            $pipeline = Pipeline::find($id);

            if (!$pipeline) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pipeline not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $history = StageHistory::getForRecord($recordId, $id);

            return response()->json([
                'success' => true,
                'history' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch record history',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reorder stages in a pipeline.
     */
    public function reorderStages(Request $request, int $id): JsonResponse
    {
        try {
            $pipeline = Pipeline::find($id);

            if (!$pipeline) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pipeline not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'stages' => 'required|array',
                'stages.*' => 'required|integer|exists:stages,id',
            ]);

            DB::transaction(function () use ($validated, $pipeline) {
                foreach ($validated['stages'] as $order => $stageId) {
                    Stage::where('id', $stageId)
                        ->where('pipeline_id', $pipeline->id)
                        ->update(['display_order' => $order]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Stages reordered successfully',
                'pipeline' => $pipeline->fresh()->load(['stages']),
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
                'message' => 'Failed to reorder stages',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sync pipeline stages to field options.
     */
    public function syncFieldOptions(int $id): JsonResponse
    {
        try {
            $pipeline = Pipeline::with(['stages', 'module'])->find($id);

            if (!$pipeline) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pipeline not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $this->fieldSyncService->syncFieldOptionsFromStages($pipeline);

            return response()->json([
                'success' => true,
                'message' => 'Field options synced successfully',
                'pipeline' => $pipeline,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync field options',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sync all pipelines' stages to their field options.
     */
    public function syncAllFieldOptions(): JsonResponse
    {
        try {
            $pipelines = Pipeline::with(['stages', 'module'])
                ->whereNotNull('stage_field_api_name')
                ->get();

            $syncedCount = 0;
            foreach ($pipelines as $pipeline) {
                $this->fieldSyncService->syncFieldOptionsFromStages($pipeline);
                $syncedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Synced field options for {$syncedCount} pipelines",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync field options',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sync stages for a pipeline (create, update, delete).
     */
    private function syncStages(Pipeline $pipeline, array $stagesData): void
    {
        $existingStageIds = $pipeline->stages->pluck('id')->toArray();
        $incomingStageIds = [];

        foreach ($stagesData as $index => $stageData) {
            $stagePayload = [
                'pipeline_id' => $pipeline->id,
                'name' => $stageData['name'],
                'color' => $stageData['color'] ?? '#6b7280',
                'probability' => $stageData['probability'] ?? 0,
                'display_order' => $stageData['display_order'] ?? $index,
                'is_won_stage' => $stageData['is_won_stage'] ?? false,
                'is_lost_stage' => $stageData['is_lost_stage'] ?? false,
                'settings' => $stageData['settings'] ?? [],
            ];

            if (!empty($stageData['id'])) {
                // Update existing stage
                $stage = Stage::find($stageData['id']);
                if ($stage && $stage->pipeline_id === $pipeline->id) {
                    $stage->update($stagePayload);
                    $incomingStageIds[] = $stage->id;
                }
            } else {
                // Create new stage
                $stage = Stage::create($stagePayload);
                $incomingStageIds[] = $stage->id;
            }
        }

        // Soft delete stages that were removed
        $stagesToDelete = array_diff($existingStageIds, $incomingStageIds);
        if (!empty($stagesToDelete)) {
            Stage::whereIn('id', $stagesToDelete)->delete();
        }
    }
}
