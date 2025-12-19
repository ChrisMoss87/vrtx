<?php

declare(strict_types=1);

namespace App\Application\Services\Pipeline;

use App\Domain\Pipeline\Repositories\PipelineRepositoryInterface;
use App\Models\Module;
use App\Models\ModuleRecord;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\StageHistory;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PipelineApplicationService
{
    public function __construct(
        private PipelineRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // PIPELINE QUERY USE CASES
    // =========================================================================

    /**
     * List pipelines with filtering
     */
    public function listPipelines(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Pipeline::query()->with(['module', 'stages', 'creator']);

        // Filter by module
        if (!empty($filters['module_id'])) {
            $query->forModule($filters['module_id']);
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Active only shortcut
        if (!empty($filters['active_only'])) {
            $query->active();
        }

        // Search by name
        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        // Sort
        $sortField = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get all pipelines (no pagination)
     */
    public function getAllPipelines(bool $activeOnly = true): Collection
    {
        $query = Pipeline::with(['module', 'stages']);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get a single pipeline with stages
     */
    public function getPipeline(int $pipelineId): ?Pipeline
    {
        return Pipeline::with([
            'module',
            'stages' => fn($q) => $q->ordered(),
            'creator',
            'updater',
        ])->find($pipelineId);
    }

    /**
     * Get pipelines for a module
     */
    public function getPipelinesForModule(int $moduleId, bool $activeOnly = true): Collection
    {
        $query = Pipeline::forModule($moduleId)->with(['stages']);

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get pipeline by module API name
     */
    public function getPipelineByModuleApiName(string $moduleApiName): ?Pipeline
    {
        $module = Module::where('api_name', $moduleApiName)->first();

        if (!$module) {
            return null;
        }

        return Pipeline::forModule($module->id)
            ->active()
            ->with(['stages' => fn($q) => $q->ordered()])
            ->first();
    }

    /**
     * Get pipeline with stage metrics (counts and values)
     */
    public function getPipelineWithMetrics(int $pipelineId, ?string $valueFieldName = null): ?array
    {
        $pipeline = $this->getPipeline($pipelineId);

        if (!$pipeline) {
            return null;
        }

        $metrics = Stage::getStageMetricsForPipeline($pipeline, $valueFieldName);

        $stagesWithMetrics = $pipeline->stages->map(function (Stage $stage) use ($metrics) {
            $stageMetrics = $metrics[(string)$stage->id] ?? ['count' => 0, 'totalValue' => 0];

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'probability' => $stage->probability,
                'display_order' => $stage->display_order,
                'is_won_stage' => $stage->is_won_stage,
                'is_lost_stage' => $stage->is_lost_stage,
                'rotting_days' => $stage->rotting_days,
                'record_count' => $stageMetrics['count'],
                'total_value' => $stageMetrics['totalValue'],
            ];
        });

        return [
            'pipeline' => $pipeline,
            'stages' => $stagesWithMetrics,
            'totals' => [
                'record_count' => $stagesWithMetrics->sum('record_count'),
                'total_value' => $stagesWithMetrics->sum('total_value'),
            ],
        ];
    }

    /**
     * Get pipeline analytics
     */
    public function getPipelineAnalytics(int $pipelineId, ?string $startDate = null, ?string $endDate = null): array
    {
        $pipeline = Pipeline::with(['stages'])->findOrFail($pipelineId);

        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(30);
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        // Get stage history within date range
        $stageHistory = StageHistory::where('pipeline_id', $pipelineId)
            ->whereBetween('created_at', [$start, $end])
            ->with(['fromStage', 'toStage'])
            ->get();

        // Stage-to-stage conversion rates
        $conversionRates = [];
        $stages = $pipeline->stages->sortBy('display_order');

        $previousStage = null;
        foreach ($stages as $stage) {
            if ($previousStage && !$previousStage->is_won_stage && !$previousStage->is_lost_stage) {
                $movedTo = $stageHistory->where('from_stage_id', $previousStage->id)
                    ->where('to_stage_id', $stage->id)
                    ->count();
                $movedFrom = $stageHistory->where('from_stage_id', $previousStage->id)->count();

                $conversionRates["{$previousStage->id}_to_{$stage->id}"] = [
                    'from_stage' => $previousStage->name,
                    'to_stage' => $stage->name,
                    'moved_count' => $movedTo,
                    'total_from' => $movedFrom,
                    'rate' => $movedFrom > 0 ? round(($movedTo / $movedFrom) * 100, 1) : 0,
                ];
            }
            $previousStage = $stage;
        }

        // Average time in each stage
        $avgTimeByStage = [];
        foreach ($stages as $stage) {
            $durations = $stageHistory->where('from_stage_id', $stage->id)
                ->whereNotNull('duration_in_stage')
                ->pluck('duration_in_stage');

            $avgTimeByStage[$stage->id] = [
                'stage_name' => $stage->name,
                'avg_seconds' => $durations->avg() ?? 0,
                'avg_hours' => round(($durations->avg() ?? 0) / 3600, 1),
                'avg_days' => round(($durations->avg() ?? 0) / 86400, 1),
                'sample_size' => $durations->count(),
            ];
        }

        // Win/loss rates
        $wonStages = $stages->where('is_won_stage', true)->pluck('id');
        $lostStages = $stages->where('is_lost_stage', true)->pluck('id');

        $wonCount = $stageHistory->whereIn('to_stage_id', $wonStages)->unique('module_record_id')->count();
        $lostCount = $stageHistory->whereIn('to_stage_id', $lostStages)->unique('module_record_id')->count();
        $closedCount = $wonCount + $lostCount;

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'conversion_rates' => $conversionRates,
            'avg_time_by_stage' => $avgTimeByStage,
            'win_loss' => [
                'won' => $wonCount,
                'lost' => $lostCount,
                'total_closed' => $closedCount,
                'win_rate' => $closedCount > 0 ? round(($wonCount / $closedCount) * 100, 1) : 0,
            ],
            'total_transitions' => $stageHistory->count(),
            'unique_records_moved' => $stageHistory->unique('module_record_id')->count(),
        ];
    }

    // =========================================================================
    // PIPELINE COMMAND USE CASES
    // =========================================================================

    /**
     * Create a new pipeline
     */
    public function createPipeline(array $data): Pipeline
    {
        return DB::transaction(function () use ($data) {
            $pipeline = Pipeline::create([
                'name' => $data['name'],
                'module_id' => $data['module_id'],
                'stage_field_api_name' => $data['stage_field_api_name'],
                'is_active' => $data['is_active'] ?? true,
                'settings' => $data['settings'] ?? [],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            // Create default stages if provided
            if (!empty($data['stages'])) {
                foreach ($data['stages'] as $index => $stageData) {
                    $pipeline->stages()->create([
                        'name' => $stageData['name'],
                        'color' => $stageData['color'] ?? '#6b7280',
                        'probability' => $stageData['probability'] ?? 0,
                        'display_order' => $stageData['display_order'] ?? $index,
                        'is_won_stage' => $stageData['is_won_stage'] ?? false,
                        'is_lost_stage' => $stageData['is_lost_stage'] ?? false,
                        'rotting_days' => $stageData['rotting_days'] ?? null,
                        'settings' => $stageData['settings'] ?? [],
                    ]);
                }
            }

            return $pipeline->load(['stages']);
        });
    }

    /**
     * Update a pipeline
     */
    public function updatePipeline(int $pipelineId, array $data): Pipeline
    {
        $pipeline = Pipeline::findOrFail($pipelineId);

        $pipeline->update([
            'name' => $data['name'] ?? $pipeline->name,
            'stage_field_api_name' => $data['stage_field_api_name'] ?? $pipeline->stage_field_api_name,
            'is_active' => $data['is_active'] ?? $pipeline->is_active,
            'settings' => $data['settings'] ?? $pipeline->settings,
            'updated_by' => Auth::id(),
        ]);

        return $pipeline->fresh(['stages', 'module']);
    }

    /**
     * Delete a pipeline
     */
    public function deletePipeline(int $pipelineId): bool
    {
        $pipeline = Pipeline::findOrFail($pipelineId);

        return DB::transaction(function () use ($pipeline) {
            // Delete stage history
            StageHistory::where('pipeline_id', $pipeline->id)->delete();

            // Delete stages
            $pipeline->stages()->delete();

            return $pipeline->delete();
        });
    }

    /**
     * Activate a pipeline
     */
    public function activatePipeline(int $pipelineId): Pipeline
    {
        $pipeline = Pipeline::findOrFail($pipelineId);

        $pipeline->update([
            'is_active' => true,
            'updated_by' => Auth::id(),
        ]);

        return $pipeline;
    }

    /**
     * Deactivate a pipeline
     */
    public function deactivatePipeline(int $pipelineId): Pipeline
    {
        $pipeline = Pipeline::findOrFail($pipelineId);

        $pipeline->update([
            'is_active' => false,
            'updated_by' => Auth::id(),
        ]);

        return $pipeline;
    }

    /**
     * Duplicate a pipeline
     */
    public function duplicatePipeline(int $pipelineId, ?string $newName = null): Pipeline
    {
        $source = Pipeline::with(['stages'])->findOrFail($pipelineId);

        return DB::transaction(function () use ($source, $newName) {
            $newPipeline = Pipeline::create([
                'name' => $newName ?? "{$source->name} (Copy)",
                'module_id' => $source->module_id,
                'stage_field_api_name' => $source->stage_field_api_name,
                'is_active' => false, // Start inactive
                'settings' => $source->settings,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($source->stages as $stage) {
                $newPipeline->stages()->create([
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'probability' => $stage->probability,
                    'display_order' => $stage->display_order,
                    'is_won_stage' => $stage->is_won_stage,
                    'is_lost_stage' => $stage->is_lost_stage,
                    'rotting_days' => $stage->rotting_days,
                    'settings' => $stage->settings,
                ]);
            }

            return $newPipeline->load(['stages']);
        });
    }

    // =========================================================================
    // STAGE QUERY USE CASES
    // =========================================================================

    /**
     * Get stages for a pipeline
     */
    public function getStages(int $pipelineId): Collection
    {
        return Stage::where('pipeline_id', $pipelineId)
            ->ordered()
            ->get();
    }

    /**
     * Get a single stage
     */
    public function getStage(int $stageId): ?Stage
    {
        return Stage::with(['pipeline'])->find($stageId);
    }

    /**
     * Get records in a stage
     */
    public function getRecordsInStage(int $stageId, int $perPage = 15): LengthAwarePaginator
    {
        $stage = Stage::with(['pipeline'])->findOrFail($stageId);
        $stageFieldName = $stage->pipeline->stage_field_api_name;

        return ModuleRecord::where('module_id', $stage->pipeline->module_id)
            ->whereRaw("data->>? = ?", [$stageFieldName, (string)$stage->id])
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * Get rotting deals (records in stage too long)
     */
    public function getRottingRecords(int $pipelineId): Collection
    {
        $pipeline = Pipeline::with(['stages'])->findOrFail($pipelineId);
        $stageFieldName = $pipeline->stage_field_api_name;

        $rottingRecords = collect();

        foreach ($pipeline->stages as $stage) {
            if (!$stage->rotting_days) {
                continue;
            }

            $threshold = Carbon::now()->subDays($stage->rotting_days);

            // Get last stage entry for each record
            $records = ModuleRecord::where('module_id', $pipeline->module_id)
                ->whereRaw("data->>? = ?", [$stageFieldName, (string)$stage->id])
                ->get();

            foreach ($records as $record) {
                $lastEntry = StageHistory::where('module_record_id', $record->id)
                    ->where('to_stage_id', $stage->id)
                    ->latest()
                    ->first();

                if ($lastEntry && $lastEntry->created_at < $threshold) {
                    $daysInStage = $lastEntry->created_at->diffInDays(now());

                    $rottingRecords->push([
                        'record' => $record,
                        'stage' => $stage,
                        'days_in_stage' => $daysInStage,
                        'rotting_threshold' => $stage->rotting_days,
                        'days_over' => $daysInStage - $stage->rotting_days,
                        'entered_stage_at' => $lastEntry->created_at,
                    ]);
                }
            }
        }

        return $rottingRecords->sortByDesc('days_over')->values();
    }

    // =========================================================================
    // STAGE COMMAND USE CASES
    // =========================================================================

    /**
     * Create a stage
     */
    public function createStage(int $pipelineId, array $data): Stage
    {
        $pipeline = Pipeline::findOrFail($pipelineId);

        $maxOrder = $pipeline->stages()->max('display_order') ?? 0;

        return $pipeline->stages()->create([
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6b7280',
            'probability' => $data['probability'] ?? 0,
            'display_order' => $data['display_order'] ?? $maxOrder + 1,
            'is_won_stage' => $data['is_won_stage'] ?? false,
            'is_lost_stage' => $data['is_lost_stage'] ?? false,
            'rotting_days' => $data['rotting_days'] ?? null,
            'settings' => $data['settings'] ?? [],
        ]);
    }

    /**
     * Update a stage
     */
    public function updateStage(int $stageId, array $data): Stage
    {
        $stage = Stage::findOrFail($stageId);

        $stage->update([
            'name' => $data['name'] ?? $stage->name,
            'color' => $data['color'] ?? $stage->color,
            'probability' => $data['probability'] ?? $stage->probability,
            'is_won_stage' => $data['is_won_stage'] ?? $stage->is_won_stage,
            'is_lost_stage' => $data['is_lost_stage'] ?? $stage->is_lost_stage,
            'rotting_days' => $data['rotting_days'] ?? $stage->rotting_days,
            'settings' => $data['settings'] ?? $stage->settings,
        ]);

        return $stage->fresh();
    }

    /**
     * Delete a stage
     */
    public function deleteStage(int $stageId): bool
    {
        $stage = Stage::findOrFail($stageId);

        return DB::transaction(function () use ($stage) {
            // Update records in this stage to have no stage
            ModuleRecord::where('module_id', $stage->pipeline->module_id)
                ->whereRaw("data->>? = ?", [$stage->pipeline->stage_field_api_name, (string)$stage->id])
                ->each(function ($record) use ($stage) {
                    $data = $record->data;
                    unset($data[$stage->pipeline->stage_field_api_name]);
                    $record->update(['data' => $data]);
                });

            return $stage->delete();
        });
    }

    /**
     * Reorder stages
     */
    public function reorderStages(int $pipelineId, array $stageOrder): Collection
    {
        return DB::transaction(function () use ($pipelineId, $stageOrder) {
            foreach ($stageOrder as $order => $stageId) {
                Stage::where('id', $stageId)
                    ->where('pipeline_id', $pipelineId)
                    ->update(['display_order' => $order]);
            }

            return Stage::where('pipeline_id', $pipelineId)->ordered()->get();
        });
    }

    // =========================================================================
    // STAGE TRANSITION USE CASES
    // =========================================================================

    /**
     * Move record to a different stage
     */
    public function moveRecordToStage(
        int $recordId,
        int $toStageId,
        ?string $reason = null
    ): array {
        $record = ModuleRecord::findOrFail($recordId);
        $toStage = Stage::with(['pipeline'])->findOrFail($toStageId);
        $pipeline = $toStage->pipeline;

        $stageFieldName = $pipeline->stage_field_api_name;
        $currentStageId = $record->data[$stageFieldName] ?? null;

        return DB::transaction(function () use ($record, $toStage, $pipeline, $stageFieldName, $currentStageId, $reason) {
            // Record the transition
            $history = StageHistory::recordTransition(
                $record->id,
                $pipeline->id,
                $currentStageId ? (int)$currentStageId : null,
                $toStage->id,
                Auth::id(),
                $reason
            );

            // Update the record
            $data = $record->data;
            $data[$stageFieldName] = (string)$toStage->id;
            $record->update(['data' => $data]);

            return [
                'record' => $record->fresh(),
                'history' => $history->load(['fromStage', 'toStage']),
                'from_stage_id' => $currentStageId,
                'to_stage_id' => $toStage->id,
            ];
        });
    }

    /**
     * Bulk move records to a stage
     */
    public function bulkMoveToStage(array $recordIds, int $toStageId, ?string $reason = null): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($recordIds as $recordId) {
            try {
                $result = $this->moveRecordToStage($recordId, $toStageId, $reason);
                $results['success'][] = [
                    'record_id' => $recordId,
                    'history_id' => $result['history']->id,
                ];
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'record_id' => $recordId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get stage history for a record
     */
    public function getRecordStageHistory(int $recordId, ?int $pipelineId = null): Collection
    {
        return StageHistory::getForRecord($recordId, $pipelineId);
    }

    /**
     * Get stage velocity (average time from first stage to won)
     */
    public function getStageVelocity(int $pipelineId, ?string $startDate = null, ?string $endDate = null): array
    {
        $pipeline = Pipeline::with(['stages'])->findOrFail($pipelineId);

        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(90);
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $wonStage = $pipeline->stages->where('is_won_stage', true)->first();

        if (!$wonStage) {
            return ['error' => 'No won stage defined'];
        }

        // Get records that were won in the period
        $wonHistory = StageHistory::where('pipeline_id', $pipelineId)
            ->where('to_stage_id', $wonStage->id)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $velocities = [];

        foreach ($wonHistory as $entry) {
            // Get first stage entry for this record
            $firstEntry = StageHistory::where('module_record_id', $entry->module_record_id)
                ->where('pipeline_id', $pipelineId)
                ->orderBy('created_at')
                ->first();

            if ($firstEntry) {
                $totalDays = $firstEntry->created_at->diffInDays($entry->created_at);
                $velocities[] = $totalDays;
            }
        }

        if (empty($velocities)) {
            return [
                'avg_days' => null,
                'median_days' => null,
                'min_days' => null,
                'max_days' => null,
                'sample_size' => 0,
            ];
        }

        sort($velocities);
        $count = count($velocities);
        $median = $count % 2 === 0
            ? ($velocities[$count / 2 - 1] + $velocities[$count / 2]) / 2
            : $velocities[floor($count / 2)];

        return [
            'avg_days' => round(array_sum($velocities) / $count, 1),
            'median_days' => round($median, 1),
            'min_days' => min($velocities),
            'max_days' => max($velocities),
            'sample_size' => $count,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get pipeline forecast based on probability
     */
    public function getPipelineForecast(int $pipelineId, string $valueFieldName): array
    {
        $data = $this->getPipelineWithMetrics($pipelineId, $valueFieldName);

        if (!$data) {
            return [];
        }

        $stages = collect($data['stages']);

        $forecast = [
            'stages' => $stages->map(function ($stage) {
                $weightedValue = $stage['total_value'] * ($stage['probability'] / 100);
                return [
                    'stage_name' => $stage['name'],
                    'probability' => $stage['probability'],
                    'total_value' => $stage['total_value'],
                    'weighted_value' => round($weightedValue, 2),
                    'record_count' => $stage['record_count'],
                ];
            })->toArray(),
            'totals' => [
                'pipeline_value' => $stages->sum('total_value'),
                'weighted_value' => round($stages->sum(fn($s) => $s['total_value'] * ($s['probability'] / 100)), 2),
                'total_records' => $stages->sum('record_count'),
            ],
        ];

        return $forecast;
    }
}
