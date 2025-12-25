<?php

declare(strict_types=1);

namespace App\Services\Forecast;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ForecastService
{
    /**
     * Get forecast summary for a user and period.
     *
     * @return array{
     *     commit: array{amount: float, count: int, deals: Collection},
     *     best_case: array{amount: float, count: int, deals: Collection},
     *     pipeline: array{amount: float, count: int, deals: Collection},
     *     weighted: array{amount: float, count: int},
     *     closed_won: array{amount: float, count: int},
     *     quota: array{amount: float, attainment: float}|null,
     *     period: array{type: string, start: string, end: string}
     * }
     */
    public function getForecastSummary(
        int $pipelineId,
        ?int $userId = null,
        string $periodType = 'month',
        ?Carbon $periodStart = null
    ): array {
        $pipeline = Pipeline::with('stages')->findOrFail($pipelineId);
        $period = $this->getPeriodDates($periodType, $periodStart);
        $valueField = $pipeline->settings['value_field'] ?? 'amount';
        $stageFieldName = $pipeline->stage_field_api_name;

        // Get all stages with their probabilities
        $stages = $pipeline->stages->keyBy('id');
        $wonStageIds = $stages->where('is_won_stage', true)->pluck('id')->toArray();
        $lostStageIds = $stages->where('is_lost_stage', true)->pluck('id')->toArray();

        // Base query for deals
        $baseQuery = DB::table('module_records')->where('module_id', $pipeline->module_id)
            ->where(function ($q) use ($period) {
                $q->whereNull('expected_close_date')
                    ->orWhereBetween('expected_close_date', [$period['start'], $period['end']]);
            });

        if ($userId) {
            $baseQuery->where('created_by', $userId);
        }

        // Get all deals for the period
        $allDeals = $baseQuery->get();

        // Categorize deals
        $commit = collect();
        $bestCase = collect();
        $pipelineDeals = collect();
        $closedWon = collect();

        foreach ($allDeals as $deal) {
            $stageId = $deal->data[$stageFieldName] ?? null;
            $stage = $stages->get($stageId);

            // Skip lost deals
            if ($stage && in_array($stage->id, $lostStageIds)) {
                continue;
            }

            // Closed won deals
            if ($stage && in_array($stage->id, $wonStageIds)) {
                $closedWon->push($deal);
                continue;
            }

            // Skip omitted deals
            if ($deal->forecast_category === ModuleRecord::FORECAST_OMITTED) {
                continue;
            }

            // Categorize by forecast category
            match ($deal->forecast_category) {
                ModuleRecord::FORECAST_COMMIT => $commit->push($deal),
                ModuleRecord::FORECAST_BEST_CASE => $bestCase->push($deal),
                default => $pipelineDeals->push($deal),
            };
        }

        // Calculate amounts
        $getDealAmount = fn ($deal) => $deal->forecast_override ?? ($deal->data[$valueField] ?? 0);

        $commitAmount = $commit->sum($getDealAmount);
        $bestCaseAmount = $bestCase->sum($getDealAmount);
        $pipelineAmount = $pipelineDeals->sum($getDealAmount);
        $closedWonAmount = $closedWon->sum($getDealAmount);

        // Calculate weighted forecast
        $weightedAmount = 0;
        foreach ([$commit, $bestCase, $pipelineDeals] as $dealGroup) {
            foreach ($dealGroup as $deal) {
                $stageId = $deal->data[$stageFieldName] ?? null;
                $stage = $stages->get($stageId);
                $probability = $stage ? ($stage->probability / 100) : 0.5;
                $amount = $getDealAmount($deal);
                $weightedAmount += $amount * $probability;
            }
        }

        // Get quota for the period
        $quota = null;
        if ($userId) {
            $quotaRecord = SalesQuota::forUser($userId)
                ->forPeriod($periodType, $period['start'])
                ->where(function ($q) use ($pipelineId) {
                    $q->whereNull('pipeline_id')
                        ->orWhere('pipeline_id', $pipelineId);
                })
                ->first();

            if ($quotaRecord) {
                $quota = [
                    'id' => $quotaRecord->id,
                    'amount' => (float) $quotaRecord->quota_amount,
                    'attainment' => $quotaRecord->getAttainment($closedWonAmount + $commitAmount),
                    'remaining' => $quotaRecord->getRemainingAmount($closedWonAmount),
                ];
            }
        }

        return [
            'commit' => [
                'amount' => round($commitAmount, 2),
                'count' => $commit->count(),
                'deals' => $commit->map(fn ($d) => $this->transformDeal($d, $stages, $stageFieldName, $valueField)),
            ],
            'best_case' => [
                'amount' => round($bestCaseAmount, 2),
                'count' => $bestCase->count(),
                'deals' => $bestCase->map(fn ($d) => $this->transformDeal($d, $stages, $stageFieldName, $valueField)),
            ],
            'pipeline' => [
                'amount' => round($pipelineAmount, 2),
                'count' => $pipelineDeals->count(),
                'deals' => $pipelineDeals->map(fn ($d) => $this->transformDeal($d, $stages, $stageFieldName, $valueField)),
            ],
            'weighted' => [
                'amount' => round($weightedAmount, 2),
                'count' => $commit->count() + $bestCase->count() + $pipelineDeals->count(),
            ],
            'closed_won' => [
                'amount' => round($closedWonAmount, 2),
                'count' => $closedWon->count(),
            ],
            'quota' => $quota,
            'period' => [
                'type' => $periodType,
                'start' => $period['start']->toDateString(),
                'end' => $period['end']->toDateString(),
            ],
        ];
    }

    /**
     * Transform a deal for the response.
     */
    protected function transformDeal(ModuleRecord $deal, Collection $stages, string $stageFieldName, string $valueField): array
    {
        $stageId = $deal->data[$stageFieldName] ?? null;
        $stage = $stages->get($stageId);

        return [
            'id' => $deal->id,
            'name' => $deal->data['name'] ?? $deal->data['title'] ?? "Record #{$deal->id}",
            'amount' => $deal->data[$valueField] ?? 0,
            'forecast_override' => $deal->forecast_override,
            'forecast_category' => $deal->forecast_category,
            'expected_close_date' => $deal->expected_close_date?->toDateString(),
            'stage' => $stage ? [
                'id' => $stage->id,
                'name' => $stage->name,
                'probability' => $stage->probability,
            ] : null,
            'owner_id' => $deal->created_by,
        ];
    }

    /**
     * Get period start and end dates.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getPeriodDates(string $periodType, ?Carbon $reference = null): array
    {
        $reference = $reference ?? now();

        return match ($periodType) {
            'week' => [
                'start' => $reference->copy()->startOfWeek(),
                'end' => $reference->copy()->endOfWeek(),
            ],
            'month' => [
                'start' => $reference->copy()->startOfMonth(),
                'end' => $reference->copy()->endOfMonth(),
            ],
            'quarter' => [
                'start' => $reference->copy()->startOfQuarter(),
                'end' => $reference->copy()->endOfQuarter(),
            ],
            'year' => [
                'start' => $reference->copy()->startOfYear(),
                'end' => $reference->copy()->endOfYear(),
            ],
            default => [
                'start' => $reference->copy()->startOfMonth(),
                'end' => $reference->copy()->endOfMonth(),
            ],
        };
    }

    /**
     * Update a deal's forecast settings.
     */
    public function updateDealForecast(
        ModuleRecord $deal,
        int $userId,
        ?string $category = null,
        ?float $override = null,
        ?Carbon $expectedCloseDate = null,
        ?string $reason = null
    ): ModuleRecord {
        DB::transaction(function () use ($deal, $userId, $category, $override, $expectedCloseDate, $reason) {
            // Track category change
            if ($category !== null && $category !== $deal->forecast_category) {
                DB::table('forecast_adjustments')->insertGetId([
                    'user_id' => $userId,
                    'module_record_id' => $deal->id,
                    'adjustment_type' => ForecastAdjustment::TYPE_CATEGORY_CHANGE,
                    'old_value' => $deal->forecast_category,
                    'new_value' => $category,
                    'reason' => $reason,
                ]);
                $deal->forecast_category = $category;
            }

            // Track amount override change
            if ($override !== null && $override != $deal->forecast_override) {
                DB::table('forecast_adjustments')->insertGetId([
                    'user_id' => $userId,
                    'module_record_id' => $deal->id,
                    'adjustment_type' => ForecastAdjustment::TYPE_AMOUNT_OVERRIDE,
                    'old_value' => (string) $deal->forecast_override,
                    'new_value' => (string) $override,
                    'reason' => $reason,
                ]);
                $deal->forecast_override = $override > 0 ? $override : null;
            }

            // Track close date change
            if ($expectedCloseDate !== null && $expectedCloseDate->toDateString() !== $deal->expected_close_date?->toDateString()) {
                DB::table('forecast_adjustments')->insertGetId([
                    'user_id' => $userId,
                    'module_record_id' => $deal->id,
                    'adjustment_type' => ForecastAdjustment::TYPE_CLOSE_DATE_CHANGE,
                    'old_value' => $deal->expected_close_date?->toDateString(),
                    'new_value' => $expectedCloseDate->toDateString(),
                    'reason' => $reason,
                ]);
                $deal->expected_close_date = $expectedCloseDate;
            }

            $deal->save();
        });

        return $deal->fresh();
    }

    /**
     * Create a forecast snapshot.
     */
    public function createSnapshot(
        int $pipelineId,
        ?int $userId = null,
        string $periodType = 'month',
        ?Carbon $periodStart = null
    ): ForecastSnapshot {
        $summary = $this->getForecastSummary($pipelineId, $userId, $periodType, $periodStart);
        $period = $this->getPeriodDates($periodType, $periodStart);

        return ForecastSnapshot::updateOrCreate(
            [
                'user_id' => $userId,
                'pipeline_id' => $pipelineId,
                'period_type' => $periodType,
                'period_start' => $period['start'],
                'snapshot_date' => now()->toDateString(),
            ],
            [
                'period_end' => $period['end'],
                'commit_amount' => $summary['commit']['amount'],
                'best_case_amount' => $summary['best_case']['amount'],
                'pipeline_amount' => $summary['pipeline']['amount'],
                'weighted_amount' => $summary['weighted']['amount'],
                'closed_won_amount' => $summary['closed_won']['amount'],
                'deal_count' => $summary['weighted']['count'],
                'metadata' => [
                    'quota' => $summary['quota'],
                ],
            ]
        );
    }

    /**
     * Get forecast history for trend analysis.
     */
    public function getForecastHistory(
        int $pipelineId,
        ?int $userId = null,
        string $periodType = 'month',
        int $limit = 12
    ): Collection {
        $query = DB::table('forecast_snapshots')->where('pipeline_id', $pipelineId)
            ->where('period_type', $periodType)
            ->orderBy('snapshot_date', 'desc')
            ->limit($limit);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get forecast accuracy over time.
     */
    public function getForecastAccuracy(
        int $pipelineId,
        ?int $userId = null,
        string $periodType = 'month',
        int $periods = 6
    ): Collection {
        // Get completed periods
        $results = collect();
        $now = now();

        for ($i = 1; $i <= $periods; $i++) {
            $periodEnd = match ($periodType) {
                'week' => $now->copy()->subWeeks($i)->endOfWeek(),
                'month' => $now->copy()->subMonths($i)->endOfMonth(),
                'quarter' => $now->copy()->subQuarters($i)->endOfQuarter(),
                'year' => $now->copy()->subYears($i)->endOfYear(),
                default => $now->copy()->subMonths($i)->endOfMonth(),
            };

            $periodStart = match ($periodType) {
                'week' => $periodEnd->copy()->startOfWeek(),
                'month' => $periodEnd->copy()->startOfMonth(),
                'quarter' => $periodEnd->copy()->startOfQuarter(),
                'year' => $periodEnd->copy()->startOfYear(),
                default => $periodEnd->copy()->startOfMonth(),
            };

            // Get the last snapshot before period end
            $query = DB::table('forecast_snapshots')->where('pipeline_id', $pipelineId)
                ->where('period_type', $periodType)
                ->where('period_start', $periodStart)
                ->orderBy('snapshot_date', 'desc')
                ->first();

            if ($query) {
                $results->push([
                    'period' => $periodStart->format('Y-m'),
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'forecasted' => (float) $query->weighted_amount,
                    'actual' => (float) $query->closed_won_amount,
                    'accuracy' => $query->accuracy,
                    'variance' => (float) $query->closed_won_amount - (float) $query->weighted_amount,
                ]);
            }
        }

        return $results->reverse()->values();
    }

    /**
     * Get deals with forecast data.
     */
    public function getDealsWithForecast(
        int $pipelineId,
        ?int $userId = null,
        string $periodType = 'month',
        ?Carbon $periodStart = null,
        ?string $category = null
    ): Collection {
        $pipeline = Pipeline::with('stages')->findOrFail($pipelineId);
        $period = $this->getPeriodDates($periodType, $periodStart);
        $valueField = $pipeline->settings['value_field'] ?? 'amount';
        $stageFieldName = $pipeline->stage_field_api_name;
        $stages = $pipeline->stages->keyBy('id');

        $query = DB::table('module_records')->where('module_id', $pipeline->module_id)
            ->where(function ($q) use ($period) {
                $q->whereNull('expected_close_date')
                    ->orWhereBetween('expected_close_date', [$period['start'], $period['end']]);
            })
            ->where('forecast_category', '!=', ModuleRecord::FORECAST_OMITTED);

        if ($userId) {
            $query->where('created_by', $userId);
        }

        if ($category) {
            $query->where('forecast_category', $category);
        }

        return $query->get()->map(fn ($deal) => $this->transformDeal($deal, $stages, $stageFieldName, $valueField));
    }
}
