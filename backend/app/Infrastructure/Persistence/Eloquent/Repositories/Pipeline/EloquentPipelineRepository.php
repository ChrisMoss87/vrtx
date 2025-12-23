<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Pipeline;

use App\Domain\Pipeline\Entities\Pipeline as PipelineEntity;
use App\Domain\Pipeline\Entities\Stage as StageEntity;
use App\Domain\Pipeline\Repositories\PipelineRepositoryInterface;
use App\Domain\Pipeline\ValueObjects\StageOutcome;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentPipelineRepository implements PipelineRepositoryInterface
{
    private const TABLE_PIPELINES = 'pipelines';
    private const TABLE_STAGES = 'stages';
    private const TABLE_STAGE_HISTORY = 'stage_history';
    private const TABLE_MODULES = 'modules';
    private const TABLE_MODULE_RECORDS = 'module_records';
    private const TABLE_USERS = 'users';

    // =========================================================================
    // BASIC CRUD (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?PipelineEntity
    {
        $row = DB::table(self::TABLE_PIPELINES)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function save(PipelineEntity $pipeline): PipelineEntity
    {
        $data = $this->toRowData($pipeline);

        if ($pipeline->getId() !== null) {
            DB::table(self::TABLE_PIPELINES)
                ->where('id', $pipeline->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $pipeline->getId();
        } else {
            $id = DB::table(self::TABLE_PIPELINES)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE_PIPELINES)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findByIdWithRelations(int $id): ?array
    {
        $row = DB::table(self::TABLE_PIPELINES)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = $this->rowToArray($row);

        // Load module
        if ($row->module_id) {
            $module = DB::table(self::TABLE_MODULES)->where('id', $row->module_id)->first();
            $result['module'] = $module ? (array) $module : null;
        }

        // Load stages ordered
        $result['stages'] = DB::table(self::TABLE_STAGES)
            ->where('pipeline_id', $id)
            ->orderBy('display_order')
            ->get()
            ->map(fn($s) => $this->rowToArray($s))
            ->all();

        // Load creator
        if ($row->created_by) {
            $creator = DB::table(self::TABLE_USERS)
                ->select('id', 'name', 'email')
                ->where('id', $row->created_by)
                ->first();
            $result['creator'] = $creator ? (array) $creator : null;
        }

        // Load updater
        if ($row->updated_by) {
            $updater = DB::table(self::TABLE_USERS)
                ->select('id', 'name', 'email')
                ->where('id', $row->updated_by)
                ->first();
            $result['updater'] = $updater ? (array) $updater : null;
        }

        return $result;
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $pipelineId = DB::table(self::TABLE_PIPELINES)->insertGetId([
                'name' => $data['name'],
                'module_id' => $data['module_id'],
                'stage_field_api_name' => $data['stage_field_api_name'],
                'is_active' => $data['is_active'] ?? true,
                'settings' => json_encode($data['settings'] ?? []),
                'created_by' => $data['created_by'],
                'updated_by' => $data['updated_by'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create default stages if provided
            if (!empty($data['stages'])) {
                foreach ($data['stages'] as $index => $stageData) {
                    DB::table(self::TABLE_STAGES)->insert([
                        'pipeline_id' => $pipelineId,
                        'name' => $stageData['name'],
                        'color' => $stageData['color'] ?? '#6b7280',
                        'probability' => $stageData['probability'] ?? 0,
                        'display_order' => $stageData['display_order'] ?? $index,
                        'is_won_stage' => $stageData['is_won_stage'] ?? false,
                        'is_lost_stage' => $stageData['is_lost_stage'] ?? false,
                        'rotting_days' => $stageData['rotting_days'] ?? null,
                        'settings' => json_encode($stageData['settings'] ?? []),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $this->findByIdWithStages($pipelineId);
        });
    }

    public function update(int $id, array $data): array
    {
        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $id)->first();

        if (!$pipeline) {
            throw new \RuntimeException("Pipeline not found: {$id}");
        }

        $updateData = [
            'updated_at' => now(),
            'updated_by' => $data['updated_by'],
        ];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['stage_field_api_name'])) {
            $updateData['stage_field_api_name'] = $data['stage_field_api_name'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }
        if (isset($data['settings'])) {
            $updateData['settings'] = json_encode($data['settings']);
        }

        DB::table(self::TABLE_PIPELINES)->where('id', $id)->update($updateData);

        return $this->findByIdWithRelations($id);
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            // Delete stage history
            DB::table(self::TABLE_STAGE_HISTORY)->where('pipeline_id', $id)->delete();

            // Delete stages
            DB::table(self::TABLE_STAGES)->where('pipeline_id', $id)->delete();

            // Delete pipeline
            return DB::table(self::TABLE_PIPELINES)->where('id', $id)->delete() > 0;
        });
    }

    // =========================================================================
    // PIPELINE QUERY METHODS
    // =========================================================================

    public function findWithFilters(array $filters, int $perPage = 15): PaginatedResult
    {
        $query = DB::table(self::TABLE_PIPELINES);

        // Filter by module
        if (!empty($filters['module_id'])) {
            $query->where('module_id', $filters['module_id']);
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Active only shortcut
        if (!empty($filters['active_only'])) {
            $query->where('is_active', true);
        }

        // Search by name
        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        // Sort
        $sortField = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortField, $sortDir);

        $total = $query->count();
        $page = $filters['page'] ?? 1;

        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn($row) => $this->rowToArrayWithRelations($row))
            ->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public function findAll(bool $activeOnly = true): array
    {
        $query = DB::table(self::TABLE_PIPELINES);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(fn($row) => $this->rowToArrayWithRelations($row))
            ->all();
    }

    public function findForModule(int $moduleId, bool $activeOnly = true): array
    {
        $query = DB::table(self::TABLE_PIPELINES)
            ->where('module_id', $moduleId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(fn($row) => $this->rowToArrayWithStages($row))
            ->all();
    }

    public function findByModuleApiName(string $moduleApiName): ?array
    {
        $module = DB::table(self::TABLE_MODULES)
            ->where('api_name', $moduleApiName)
            ->first();

        if (!$module) {
            return null;
        }

        $pipeline = DB::table(self::TABLE_PIPELINES)
            ->where('module_id', $module->id)
            ->where('is_active', true)
            ->first();

        if (!$pipeline) {
            return null;
        }

        return $this->rowToArrayWithStages($pipeline);
    }

    public function findWithMetrics(int $pipelineId, ?string $valueFieldName = null): ?array
    {
        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $pipelineId)->first();

        if (!$pipeline) {
            return null;
        }

        $stages = DB::table(self::TABLE_STAGES)
            ->where('pipeline_id', $pipelineId)
            ->orderBy('display_order')
            ->get();

        $stageFieldName = $pipeline->stage_field_api_name;
        $metrics = [];

        foreach ($stages as $stage) {
            $recordsQuery = DB::table(self::TABLE_MODULE_RECORDS)
                ->where('module_id', $pipeline->module_id)
                ->whereRaw("data->>? = ?", [$stageFieldName, (string)$stage->id]);

            $count = $recordsQuery->count();
            $totalValue = 0;

            if ($valueFieldName) {
                $totalValue = (float) DB::table(self::TABLE_MODULE_RECORDS)
                    ->where('module_id', $pipeline->module_id)
                    ->whereRaw("data->>? = ?", [$stageFieldName, (string)$stage->id])
                    ->selectRaw("SUM((data->>?)::numeric) as total", [$valueFieldName])
                    ->value('total') ?? 0;
            }

            $metrics[(string)$stage->id] = ['count' => $count, 'totalValue' => $totalValue];
        }

        $stagesWithMetrics = $stages->map(function ($stage) use ($metrics) {
            $stageMetrics = $metrics[(string)$stage->id] ?? ['count' => 0, 'totalValue' => 0];

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'probability' => $stage->probability,
                'display_order' => $stage->display_order,
                'is_won_stage' => (bool) $stage->is_won_stage,
                'is_lost_stage' => (bool) $stage->is_lost_stage,
                'rotting_days' => $stage->rotting_days,
                'record_count' => $stageMetrics['count'],
                'total_value' => $stageMetrics['totalValue'],
            ];
        });

        return [
            'pipeline' => $this->rowToArray($pipeline),
            'stages' => $stagesWithMetrics->all(),
            'totals' => [
                'record_count' => $stagesWithMetrics->sum('record_count'),
                'total_value' => $stagesWithMetrics->sum('total_value'),
            ],
        ];
    }

    public function getAnalytics(
        int $pipelineId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $pipelineId)->first();

        if (!$pipeline) {
            throw new \RuntimeException("Pipeline not found: {$pipelineId}");
        }

        $stages = DB::table(self::TABLE_STAGES)
            ->where('pipeline_id', $pipelineId)
            ->orderBy('display_order')
            ->get();

        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(30);
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        // Get stage history within date range
        $stageHistory = DB::table(self::TABLE_STAGE_HISTORY)
            ->where('pipeline_id', $pipelineId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        // Stage-to-stage conversion rates
        $conversionRates = [];
        $previousStage = null;

        foreach ($stages as $stage) {
            if ($previousStage && !$previousStage->is_won_stage && !$previousStage->is_lost_stage) {
                $movedTo = $stageHistory
                    ->where('from_stage_id', $previousStage->id)
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
            $durations = $stageHistory
                ->where('from_stage_id', $stage->id)
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
        $wonStageIds = $stages->where('is_won_stage', true)->pluck('id');
        $lostStageIds = $stages->where('is_lost_stage', true)->pluck('id');

        $wonCount = $stageHistory->whereIn('to_stage_id', $wonStageIds)->unique('module_record_id')->count();
        $lostCount = $stageHistory->whereIn('to_stage_id', $lostStageIds)->unique('module_record_id')->count();
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

    public function duplicate(int $pipelineId, ?string $newName = null, int $userId): array
    {
        $source = DB::table(self::TABLE_PIPELINES)->where('id', $pipelineId)->first();

        if (!$source) {
            throw new \RuntimeException("Pipeline not found: {$pipelineId}");
        }

        $sourceStages = DB::table(self::TABLE_STAGES)
            ->where('pipeline_id', $pipelineId)
            ->get();

        return DB::transaction(function () use ($source, $sourceStages, $newName, $userId) {
            $newPipelineId = DB::table(self::TABLE_PIPELINES)->insertGetId([
                'name' => $newName ?? "{$source->name} (Copy)",
                'module_id' => $source->module_id,
                'stage_field_api_name' => $source->stage_field_api_name,
                'is_active' => false, // Start inactive
                'settings' => $source->settings,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($sourceStages as $stage) {
                DB::table(self::TABLE_STAGES)->insert([
                    'pipeline_id' => $newPipelineId,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'probability' => $stage->probability,
                    'display_order' => $stage->display_order,
                    'is_won_stage' => $stage->is_won_stage,
                    'is_lost_stage' => $stage->is_lost_stage,
                    'rotting_days' => $stage->rotting_days,
                    'settings' => $stage->settings,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $this->findByIdWithStages($newPipelineId);
        });
    }

    // =========================================================================
    // STAGE QUERY METHODS
    // =========================================================================

    public function findStages(int $pipelineId): array
    {
        return DB::table(self::TABLE_STAGES)
            ->where('pipeline_id', $pipelineId)
            ->orderBy('display_order')
            ->get()
            ->map(fn($row) => $this->rowToArray($row))
            ->all();
    }

    public function findStageById(int $stageId): ?array
    {
        $stage = DB::table(self::TABLE_STAGES)->where('id', $stageId)->first();

        if (!$stage) {
            return null;
        }

        $result = $this->rowToArray($stage);

        // Load pipeline
        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $stage->pipeline_id)->first();
        $result['pipeline'] = $pipeline ? $this->rowToArray($pipeline) : null;

        return $result;
    }

    public function findRecordsInStage(int $stageId, int $perPage = 15): PaginatedResult
    {
        $stage = DB::table(self::TABLE_STAGES)->where('id', $stageId)->first();

        if (!$stage) {
            throw new \RuntimeException("Stage not found: {$stageId}");
        }

        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $stage->pipeline_id)->first();
        $stageFieldName = $pipeline->stage_field_api_name;

        $query = DB::table(self::TABLE_MODULE_RECORDS)
            ->where('module_id', $pipeline->module_id)
            ->whereRaw("data->>? = ?", [$stageFieldName, (string)$stage->id])
            ->orderByDesc('updated_at');

        $total = $query->count();
        $items = $query
            ->offset(0)
            ->limit($perPage)
            ->get()
            ->map(fn($row) => $this->rowToArray($row))
            ->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: 1,
        );
    }

    public function findRottingRecords(int $pipelineId): array
    {
        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $pipelineId)->first();

        if (!$pipeline) {
            throw new \RuntimeException("Pipeline not found: {$pipelineId}");
        }

        $stages = DB::table(self::TABLE_STAGES)
            ->where('pipeline_id', $pipelineId)
            ->get();

        $stageFieldName = $pipeline->stage_field_api_name;
        $rottingRecords = collect();

        foreach ($stages as $stage) {
            if (!$stage->rotting_days) {
                continue;
            }

            $threshold = Carbon::now()->subDays($stage->rotting_days);

            // Get records in this stage
            $records = DB::table(self::TABLE_MODULE_RECORDS)
                ->where('module_id', $pipeline->module_id)
                ->whereRaw("data->>? = ?", [$stageFieldName, (string)$stage->id])
                ->get();

            foreach ($records as $record) {
                $lastEntry = DB::table(self::TABLE_STAGE_HISTORY)
                    ->where('module_record_id', $record->id)
                    ->where('to_stage_id', $stage->id)
                    ->orderByDesc('created_at')
                    ->first();

                if ($lastEntry && Carbon::parse($lastEntry->created_at) < $threshold) {
                    $daysInStage = Carbon::parse($lastEntry->created_at)->diffInDays(now());

                    $rottingRecords->push([
                        'record' => $this->rowToArray($record),
                        'stage' => $this->rowToArray($stage),
                        'days_in_stage' => $daysInStage,
                        'rotting_threshold' => $stage->rotting_days,
                        'days_over' => $daysInStage - $stage->rotting_days,
                        'entered_stage_at' => $lastEntry->created_at,
                    ]);
                }
            }
        }

        return $rottingRecords->sortByDesc('days_over')->values()->all();
    }

    // =========================================================================
    // STAGE COMMAND METHODS
    // =========================================================================

    public function createStage(int $pipelineId, array $data): array
    {
        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $pipelineId)->first();

        if (!$pipeline) {
            throw new \RuntimeException("Pipeline not found: {$pipelineId}");
        }

        $maxOrder = DB::table(self::TABLE_STAGES)
            ->where('pipeline_id', $pipelineId)
            ->max('display_order') ?? 0;

        $stageId = DB::table(self::TABLE_STAGES)->insertGetId([
            'pipeline_id' => $pipelineId,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6b7280',
            'probability' => $data['probability'] ?? 0,
            'display_order' => $data['display_order'] ?? $maxOrder + 1,
            'is_won_stage' => $data['is_won_stage'] ?? false,
            'is_lost_stage' => $data['is_lost_stage'] ?? false,
            'rotting_days' => $data['rotting_days'] ?? null,
            'settings' => json_encode($data['settings'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stage = DB::table(self::TABLE_STAGES)->where('id', $stageId)->first();

        return $this->rowToArray($stage);
    }

    public function updateStage(int $stageId, array $data): array
    {
        $stage = DB::table(self::TABLE_STAGES)->where('id', $stageId)->first();

        if (!$stage) {
            throw new \RuntimeException("Stage not found: {$stageId}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }
        if (isset($data['probability'])) {
            $updateData['probability'] = $data['probability'];
        }
        if (isset($data['is_won_stage'])) {
            $updateData['is_won_stage'] = $data['is_won_stage'];
        }
        if (isset($data['is_lost_stage'])) {
            $updateData['is_lost_stage'] = $data['is_lost_stage'];
        }
        if (isset($data['rotting_days'])) {
            $updateData['rotting_days'] = $data['rotting_days'];
        }
        if (isset($data['settings'])) {
            $updateData['settings'] = json_encode($data['settings']);
        }

        DB::table(self::TABLE_STAGES)->where('id', $stageId)->update($updateData);

        $updatedStage = DB::table(self::TABLE_STAGES)->where('id', $stageId)->first();

        return $this->rowToArray($updatedStage);
    }

    public function deleteStage(int $stageId): bool
    {
        $stage = DB::table(self::TABLE_STAGES)->where('id', $stageId)->first();

        if (!$stage) {
            throw new \RuntimeException("Stage not found: {$stageId}");
        }

        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $stage->pipeline_id)->first();

        return DB::transaction(function () use ($stage, $pipeline) {
            // Get records in this stage and remove stage value
            $stageFieldName = $pipeline->stage_field_api_name;

            $records = DB::table(self::TABLE_MODULE_RECORDS)
                ->where('module_id', $pipeline->module_id)
                ->whereRaw("data->>? = ?", [$stageFieldName, (string)$stage->id])
                ->get();

            foreach ($records as $record) {
                $data = json_decode($record->data, true);
                unset($data[$stageFieldName]);
                DB::table(self::TABLE_MODULE_RECORDS)
                    ->where('id', $record->id)
                    ->update(['data' => json_encode($data), 'updated_at' => now()]);
            }

            return DB::table(self::TABLE_STAGES)->where('id', $stage->id)->delete() > 0;
        });
    }

    public function reorderStages(int $pipelineId, array $stageOrder): array
    {
        return DB::transaction(function () use ($pipelineId, $stageOrder) {
            foreach ($stageOrder as $order => $stageId) {
                DB::table(self::TABLE_STAGES)
                    ->where('id', $stageId)
                    ->where('pipeline_id', $pipelineId)
                    ->update(['display_order' => $order, 'updated_at' => now()]);
            }

            return DB::table(self::TABLE_STAGES)
                ->where('pipeline_id', $pipelineId)
                ->orderBy('display_order')
                ->get()
                ->map(fn($row) => $this->rowToArray($row))
                ->all();
        });
    }

    // =========================================================================
    // STAGE TRANSITION METHODS
    // =========================================================================

    public function moveRecordToStage(
        int $recordId,
        int $toStageId,
        int $userId,
        ?string $reason = null
    ): array {
        $record = DB::table(self::TABLE_MODULE_RECORDS)->where('id', $recordId)->first();

        if (!$record) {
            throw new \RuntimeException("Record not found: {$recordId}");
        }

        $toStage = DB::table(self::TABLE_STAGES)->where('id', $toStageId)->first();

        if (!$toStage) {
            throw new \RuntimeException("Stage not found: {$toStageId}");
        }

        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $toStage->pipeline_id)->first();
        $stageFieldName = $pipeline->stage_field_api_name;
        $recordData = json_decode($record->data, true);
        $currentStageId = $recordData[$stageFieldName] ?? null;

        return DB::transaction(function () use ($record, $toStage, $pipeline, $stageFieldName, $currentStageId, $userId, $reason, $recordData) {
            // Calculate duration in previous stage
            $durationInStage = null;
            if ($currentStageId) {
                $lastEntry = DB::table(self::TABLE_STAGE_HISTORY)
                    ->where('module_record_id', $record->id)
                    ->where('to_stage_id', (int) $currentStageId)
                    ->orderByDesc('created_at')
                    ->first();

                if ($lastEntry) {
                    $durationInStage = Carbon::parse($lastEntry->created_at)->diffInSeconds(now());
                }
            }

            // Record the transition
            $historyId = DB::table(self::TABLE_STAGE_HISTORY)->insertGetId([
                'module_record_id' => $record->id,
                'pipeline_id' => $pipeline->id,
                'from_stage_id' => $currentStageId ? (int) $currentStageId : null,
                'to_stage_id' => $toStage->id,
                'user_id' => $userId,
                'reason' => $reason,
                'duration_in_stage' => $durationInStage,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update the record
            $recordData[$stageFieldName] = (string)$toStage->id;
            DB::table(self::TABLE_MODULE_RECORDS)
                ->where('id', $record->id)
                ->update(['data' => json_encode($recordData), 'updated_at' => now()]);

            $updatedRecord = DB::table(self::TABLE_MODULE_RECORDS)->where('id', $record->id)->first();
            $history = DB::table(self::TABLE_STAGE_HISTORY)->where('id', $historyId)->first();

            // Load from/to stage names for history
            $historyArray = $this->rowToArray($history);
            if ($history->from_stage_id) {
                $fromStage = DB::table(self::TABLE_STAGES)->where('id', $history->from_stage_id)->first();
                $historyArray['from_stage'] = $fromStage ? $this->rowToArray($fromStage) : null;
            }
            $historyArray['to_stage'] = $this->rowToArray($toStage);

            return [
                'record' => $this->rowToArray($updatedRecord),
                'history' => $historyArray,
                'from_stage_id' => $currentStageId,
                'to_stage_id' => $toStage->id,
            ];
        });
    }

    public function findRecordStageHistory(int $recordId, ?int $pipelineId = null): array
    {
        $query = DB::table(self::TABLE_STAGE_HISTORY)
            ->where('module_record_id', $recordId)
            ->orderByDesc('created_at');

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        return $query->get()->map(function ($row) {
            $result = $this->rowToArray($row);

            // Load stage names
            if ($row->from_stage_id) {
                $fromStage = DB::table(self::TABLE_STAGES)->where('id', $row->from_stage_id)->first();
                $result['from_stage'] = $fromStage ? $this->rowToArray($fromStage) : null;
            }
            if ($row->to_stage_id) {
                $toStage = DB::table(self::TABLE_STAGES)->where('id', $row->to_stage_id)->first();
                $result['to_stage'] = $toStage ? $this->rowToArray($toStage) : null;
            }

            return $result;
        })->all();
    }

    public function getStageVelocity(
        int $pipelineId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $pipelineId)->first();

        if (!$pipeline) {
            throw new \RuntimeException("Pipeline not found: {$pipelineId}");
        }

        $stages = DB::table(self::TABLE_STAGES)->where('pipeline_id', $pipelineId)->get();

        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(90);
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $wonStage = $stages->where('is_won_stage', true)->first();

        if (!$wonStage) {
            return ['error' => 'No won stage defined'];
        }

        // Get records that were won in the period
        $wonHistory = DB::table(self::TABLE_STAGE_HISTORY)
            ->where('pipeline_id', $pipelineId)
            ->where('to_stage_id', $wonStage->id)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $velocities = [];

        foreach ($wonHistory as $entry) {
            // Get first stage entry for this record
            $firstEntry = DB::table(self::TABLE_STAGE_HISTORY)
                ->where('module_record_id', $entry->module_record_id)
                ->where('pipeline_id', $pipelineId)
                ->orderBy('created_at')
                ->first();

            if ($firstEntry) {
                $totalDays = Carbon::parse($firstEntry->created_at)->diffInDays(Carbon::parse($entry->created_at));
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

    public function getForecast(int $pipelineId, string $valueFieldName): array
    {
        $data = $this->findWithMetrics($pipelineId, $valueFieldName);

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
            })->all(),
            'totals' => [
                'pipeline_value' => $stages->sum('total_value'),
                'weighted_value' => round($stages->sum(fn($s) => $s['total_value'] * ($s['probability'] / 100)), 2),
                'total_records' => $stages->sum('record_count'),
            ],
        ];

        return $forecast;
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function findByIdWithStages(int $pipelineId): array
    {
        $pipeline = DB::table(self::TABLE_PIPELINES)->where('id', $pipelineId)->first();
        $result = $this->rowToArray($pipeline);

        $result['stages'] = DB::table(self::TABLE_STAGES)
            ->where('pipeline_id', $pipelineId)
            ->orderBy('display_order')
            ->get()
            ->map(fn($row) => $this->rowToArray($row))
            ->all();

        return $result;
    }

    private function rowToArray(stdClass $row): array
    {
        $array = (array) $row;

        // Handle JSON fields
        if (isset($array['settings']) && is_string($array['settings'])) {
            $array['settings'] = json_decode($array['settings'], true);
        }
        if (isset($array['data']) && is_string($array['data'])) {
            $array['data'] = json_decode($array['data'], true);
        }

        return $array;
    }

    private function rowToArrayWithStages(stdClass $row): array
    {
        $result = $this->rowToArray($row);

        $result['stages'] = DB::table(self::TABLE_STAGES)
            ->where('pipeline_id', $row->id)
            ->orderBy('display_order')
            ->get()
            ->map(fn($s) => $this->rowToArray($s))
            ->all();

        return $result;
    }

    private function rowToArrayWithRelations(stdClass $row): array
    {
        $result = $this->rowToArray($row);

        // Load module
        if ($row->module_id) {
            $module = DB::table(self::TABLE_MODULES)->where('id', $row->module_id)->first();
            $result['module'] = $module ? (array) $module : null;
        }

        // Load stages
        $result['stages'] = DB::table(self::TABLE_STAGES)
            ->where('pipeline_id', $row->id)
            ->orderBy('display_order')
            ->get()
            ->map(fn($s) => $this->rowToArray($s))
            ->all();

        // Load creator
        if ($row->created_by) {
            $creator = DB::table(self::TABLE_USERS)
                ->select('id', 'name', 'email')
                ->where('id', $row->created_by)
                ->first();
            $result['creator'] = $creator ? (array) $creator : null;
        }

        return $result;
    }

    // =========================================================================
    // MAPPING METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $row): PipelineEntity
    {
        return PipelineEntity::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            moduleId: (int) $row->module_id,
            stageFieldApiName: $row->stage_field_api_name,
            isActive: (bool) $row->is_active,
            settings: $row->settings ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [],
            createdBy: $row->created_by ? (int) $row->created_by : null,
            updatedBy: $row->updated_by ? (int) $row->updated_by : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
            deletedAt: isset($row->deleted_at) && $row->deleted_at ? new DateTimeImmutable($row->deleted_at) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRowData(PipelineEntity $pipeline): array
    {
        return [
            'name' => $pipeline->getName(),
            'module_id' => $pipeline->getModuleId(),
            'stage_field_api_name' => $pipeline->getStageFieldApiName(),
            'is_active' => $pipeline->isActive(),
            'settings' => json_encode($pipeline->getSettings()),
            'created_by' => $pipeline->getCreatedBy(),
            'updated_by' => $pipeline->getUpdatedBy(),
        ];
    }
}
