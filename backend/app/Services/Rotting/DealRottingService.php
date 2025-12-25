<?php

declare(strict_types=1);

namespace App\Services\Rotting;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DealRottingService
{
    // Rot status thresholds (percentage of rotting_days)
    public const STATUS_FRESH = 'fresh';         // 0-50%
    public const STATUS_WARMING = 'warming';     // 50-75%
    public const STATUS_STALE = 'stale';         // 75-100%
    public const STATUS_ROTTING = 'rotting';     // >100%

    /**
     * Get the rot status for a single record.
     *
     * @return array{status: string, days_inactive: int, threshold_days: int|null, percentage: float, color: string}
     */
    public function getRecordRotStatus(ModuleRecord $record, Stage $stage, bool $excludeWeekends = false): array
    {
        $thresholdDays = $stage->rotting_days;

        // If no threshold configured, record is fresh
        if ($thresholdDays === null || $thresholdDays <= 0) {
            return [
                'status' => self::STATUS_FRESH,
                'days_inactive' => 0,
                'threshold_days' => null,
                'percentage' => 0,
                'color' => 'green',
            ];
        }

        $daysInactive = $this->calculateDaysInactive($record, $excludeWeekends);
        $percentage = ($daysInactive / $thresholdDays) * 100;

        return [
            'status' => $this->getStatusFromPercentage($percentage),
            'days_inactive' => $daysInactive,
            'threshold_days' => $thresholdDays,
            'percentage' => round($percentage, 1),
            'color' => $this->getColorFromPercentage($percentage),
        ];
    }

    /**
     * Calculate days since last activity.
     */
    public function calculateDaysInactive(ModuleRecord $record, bool $excludeWeekends = false): int
    {
        $lastActivity = $record->last_activity_at ?? $record->updated_at ?? $record->created_at;

        if (!$lastActivity) {
            return 0;
        }

        $lastActivityDate = Carbon::parse($lastActivity);
        $now = Carbon::now();

        if ($excludeWeekends) {
            return $this->calculateBusinessDays($lastActivityDate, $now);
        }

        return (int) $lastActivityDate->diffInDays($now);
    }

    /**
     * Calculate business days (excluding weekends).
     */
    protected function calculateBusinessDays(Carbon $start, Carbon $end): int
    {
        $businessDays = 0;
        $current = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        while ($current->lt($end)) {
            if (!$current->isWeekend()) {
                $businessDays++;
            }
            $current->addDay();
        }

        return $businessDays;
    }

    /**
     * Get status from percentage of threshold.
     */
    protected function getStatusFromPercentage(float $percentage): string
    {
        return match (true) {
            $percentage >= 100 => self::STATUS_ROTTING,
            $percentage >= 75 => self::STATUS_STALE,
            $percentage >= 50 => self::STATUS_WARMING,
            default => self::STATUS_FRESH,
        };
    }

    /**
     * Get color from percentage of threshold.
     */
    protected function getColorFromPercentage(float $percentage): string
    {
        return match (true) {
            $percentage >= 100 => 'red',
            $percentage >= 75 => 'orange',
            $percentage >= 50 => 'yellow',
            default => 'green',
        };
    }

    /**
     * Get alert type from percentage of threshold.
     */
    protected function getAlertTypeFromPercentage(float $percentage): ?string
    {
        return match (true) {
            $percentage >= 100 => RottingAlert::TYPE_ROTTING,
            $percentage >= 75 => RottingAlert::TYPE_STALE,
            $percentage >= 50 => RottingAlert::TYPE_WARNING,
            default => null,
        };
    }

    /**
     * Get all rotting deals for a pipeline.
     *
     * @return Collection<int, array{record: ModuleRecord, stage: Stage, rot_status: array}>
     */
    public function getRottingDealsForPipeline(Pipeline $pipeline, bool $excludeWeekends = false): Collection
    {
        $stages = $pipeline->stages()->whereNotNull('rotting_days')->get()->keyBy('id');

        if ($stages->isEmpty()) {
            return collect();
        }

        $stageFieldName = $pipeline->stage_field_api_name;

        // Get all records in stages with rotting thresholds
        $records = DB::table('module_records')->where('module_id', $pipeline->module_id)
            ->whereIn(
                \DB::raw("data->>'{$stageFieldName}'"),
                $stages->keys()->map(fn ($id) => (string) $id)->toArray()
            )
            ->get();

        $rottingDeals = collect();

        foreach ($records as $record) {
            $stageId = $record->data[$stageFieldName] ?? null;
            $stage = $stages->get($stageId);

            if (!$stage) {
                continue;
            }

            $rotStatus = $this->getRecordRotStatus($record, $stage, $excludeWeekends);

            // Only include if not fresh
            if ($rotStatus['status'] !== self::STATUS_FRESH) {
                $rottingDeals->push([
                    'record' => $record,
                    'stage' => $stage,
                    'rot_status' => $rotStatus,
                ]);
            }
        }

        // Sort by severity (most rotted first)
        return $rottingDeals->sortByDesc(fn ($item) => $item['rot_status']['percentage']);
    }

    /**
     * Get rotting deals for a specific user (owned by them).
     *
     * @return Collection<int, array{record: ModuleRecord, stage: Stage, pipeline: Pipeline, rot_status: array}>
     */
    public function getRottingDealsForUser(int $userId, ?int $pipelineId = null): Collection
    {
        $settings = RottingAlertSetting::getEffectiveForUser($userId, $pipelineId);
        $excludeWeekends = $settings->exclude_weekends;

        $pipelineQuery = Pipeline::with(['stages' => fn ($q) => $q->whereNotNull('rotting_days')]);

        if ($pipelineId) {
            $pipelineQuery->where('id', $pipelineId);
        }

        $pipelines = $pipelineQuery->get();
        $rottingDeals = collect();

        foreach ($pipelines as $pipeline) {
            $stages = $pipeline->stages->keyBy('id');

            if ($stages->isEmpty()) {
                continue;
            }

            $stageFieldName = $pipeline->stage_field_api_name;

            // Get records owned by this user in stages with rotting thresholds
            $records = DB::table('module_records')->where('module_id', $pipeline->module_id)
                ->where('created_by', $userId)
                ->whereIn(
                    \DB::raw("data->>'{$stageFieldName}'"),
                    $stages->keys()->map(fn ($id) => (string) $id)->toArray()
                )
                ->get();

            foreach ($records as $record) {
                $stageId = $record->data[$stageFieldName] ?? null;
                $stage = $stages->get($stageId);

                if (!$stage) {
                    continue;
                }

                $rotStatus = $this->getRecordRotStatus($record, $stage, $excludeWeekends);

                if ($rotStatus['status'] !== self::STATUS_FRESH) {
                    $rottingDeals->push([
                        'record' => $record,
                        'stage' => $stage,
                        'pipeline' => $pipeline,
                        'rot_status' => $rotStatus,
                    ]);
                }
            }
        }

        return $rottingDeals->sortByDesc(fn ($item) => $item['rot_status']['percentage']);
    }

    /**
     * Check all deals and create alerts where needed.
     *
     * @return array{alerts_created: int, deals_checked: int}
     */
    public function checkAndCreateAlerts(): array
    {
        $alertsCreated = 0;
        $dealsChecked = 0;

        $pipelines = Pipeline::with(['stages' => fn ($q) => $q->whereNotNull('rotting_days')])->get();

        foreach ($pipelines as $pipeline) {
            $stages = $pipeline->stages->keyBy('id');

            if ($stages->isEmpty()) {
                continue;
            }

            $stageFieldName = $pipeline->stage_field_api_name;

            $records = ModuleRecord::with('creator')
                ->where('module_id', $pipeline->module_id)
                ->whereIn(
                    \DB::raw("data->>'{$stageFieldName}'"),
                    $stages->keys()->map(fn ($id) => (string) $id)->toArray()
                )
                ->get();

            foreach ($records as $record) {
                $dealsChecked++;
                $stageId = $record->data[$stageFieldName] ?? null;
                $stage = $stages->get($stageId);

                if (!$stage || !$record->created_by) {
                    continue;
                }

                // Get user's settings
                $settings = RottingAlertSetting::getEffectiveForUser($record->created_by, $pipeline->id);
                $rotStatus = $this->getRecordRotStatus($record, $stage, $settings->exclude_weekends);
                $alertType = $this->getAlertTypeFromPercentage($rotStatus['percentage']);

                if (!$alertType) {
                    continue;
                }

                // Check if alert already exists for this record/stage/type
                $existingAlert = DB::table('rotting_alerts')->where('module_record_id', $record->id)
                    ->where('stage_id', $stage->id)
                    ->where('alert_type', $alertType)
                    ->first();

                if ($existingAlert) {
                    continue;
                }

                // Create new alert
                DB::table('rotting_alerts')->insertGetId([
                    'module_record_id' => $record->id,
                    'stage_id' => $stage->id,
                    'user_id' => $record->created_by,
                    'alert_type' => $alertType,
                    'days_inactive' => $rotStatus['days_inactive'],
                    'sent_at' => now(),
                ]);

                $alertsCreated++;
            }
        }

        return [
            'alerts_created' => $alertsCreated,
            'deals_checked' => $dealsChecked,
        ];
    }

    /**
     * Update last_activity_at for a record.
     */
    public function recordActivity(ModuleRecord $record): bool
    {
        return $record->update(['last_activity_at' => now()]);
    }

    /**
     * Get summary statistics for rotting deals.
     *
     * @return array{total: int, fresh: int, warming: int, stale: int, rotting: int}
     */
    public function getSummaryStats(Pipeline $pipeline, bool $excludeWeekends = false): array
    {
        $stats = [
            'total' => 0,
            'fresh' => 0,
            'warming' => 0,
            'stale' => 0,
            'rotting' => 0,
        ];

        $stages = $pipeline->stages()->whereNotNull('rotting_days')->get()->keyBy('id');

        if ($stages->isEmpty()) {
            return $stats;
        }

        $stageFieldName = $pipeline->stage_field_api_name;

        $records = DB::table('module_records')->where('module_id', $pipeline->module_id)
            ->whereIn(
                \DB::raw("data->>'{$stageFieldName}'"),
                $stages->keys()->map(fn ($id) => (string) $id)->toArray()
            )
            ->get();

        foreach ($records as $record) {
            $stats['total']++;
            $stageId = $record->data[$stageFieldName] ?? null;
            $stage = $stages->get($stageId);

            if (!$stage) {
                $stats['fresh']++;
                continue;
            }

            $rotStatus = $this->getRecordRotStatus($record, $stage, $excludeWeekends);
            $stats[$rotStatus['status']]++;
        }

        return $stats;
    }
}
