<?php

declare(strict_types=1);

namespace App\Services\Scenario;

use App\Models\ForecastScenario;
use Illuminate\Support\Collection;

class ScenarioCalculatorService
{
    /**
     * Calculate detailed metrics for a scenario.
     */
    public function calculateMetrics(ForecastScenario $scenario): array
    {
        $deals = $scenario->activeDeals()->with('stage')->get();
        $committedDeals = $deals->where('is_committed', true);
        $openDeals = $deals->where('is_committed', false);

        $totalUnweighted = $deals->sum('amount');
        $totalWeighted = $deals->sum(fn($d) => $d->getWeightedAmount());
        $committedTotal = $committedDeals->sum('amount');

        $avgProbability = $deals->count() > 0
            ? $deals->avg('probability') ?? 50
            : 0;

        $avgDealSize = $deals->count() > 0
            ? $totalUnweighted / $deals->count()
            : 0;

        // Stage breakdown
        $byStage = $deals->groupBy('stage_id')->map(function ($stageDeals, $stageId) {
            $stage = $stageDeals->first()->stage;
            return [
                'stage_id' => $stageId,
                'stage_name' => $stage?->name ?? 'Unknown',
                'deal_count' => $stageDeals->count(),
                'total_amount' => $stageDeals->sum('amount'),
                'weighted_amount' => $stageDeals->sum(fn($d) => $d->getWeightedAmount()),
            ];
        })->values();

        // Time-based projection
        $timelineProjection = $this->calculateTimelineProjection($deals, $scenario);

        return [
            'deal_count' => $deals->count(),
            'committed_count' => $committedDeals->count(),
            'open_count' => $openDeals->count(),
            'total_unweighted' => round($totalUnweighted, 2),
            'total_weighted' => round($totalWeighted, 2),
            'committed_total' => round($committedTotal, 2),
            'average_probability' => round($avgProbability, 1),
            'average_deal_size' => round($avgDealSize, 2),
            'target_amount' => $scenario->target_amount,
            'gap_amount' => $scenario->getGapAmount(),
            'progress_percent' => round($scenario->getProgressPercent(), 1),
            'by_stage' => $byStage,
            'timeline' => $timelineProjection,
        ];
    }

    /**
     * Calculate timeline projection for a scenario.
     */
    protected function calculateTimelineProjection(Collection $deals, ForecastScenario $scenario): array
    {
        $start = $scenario->period_start;
        $end = $scenario->period_end;
        $timeline = [];

        // Group deals by close date (weekly buckets)
        $current = $start->copy();
        $cumulative = 0;
        $cumulativeWeighted = 0;

        while ($current <= $end) {
            $weekEnd = $current->copy()->addDays(6);

            $weekDeals = $deals->filter(function ($deal) use ($current, $weekEnd) {
                $closeDate = $deal->close_date;
                return $closeDate && $closeDate >= $current && $closeDate <= $weekEnd;
            });

            $weekTotal = $weekDeals->sum('amount');
            $weekWeighted = $weekDeals->sum(fn($d) => $d->getWeightedAmount());
            $cumulative += $weekTotal;
            $cumulativeWeighted += $weekWeighted;

            $timeline[] = [
                'week_start' => $current->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'deal_count' => $weekDeals->count(),
                'amount' => round($weekTotal, 2),
                'weighted' => round($weekWeighted, 2),
                'cumulative' => round($cumulative, 2),
                'cumulative_weighted' => round($cumulativeWeighted, 2),
            ];

            $current->addDays(7);
        }

        return $timeline;
    }

    /**
     * Compare multiple scenarios.
     */
    public function compareScenarios(array $scenarioIds): array
    {
        $scenarios = ForecastScenario::whereIn('id', $scenarioIds)
            ->with(['deals.stage'])
            ->get();

        $comparison = [];

        foreach ($scenarios as $scenario) {
            $metrics = $this->calculateMetrics($scenario);
            $comparison[] = [
                'scenario_id' => $scenario->id,
                'scenario_name' => $scenario->name,
                'scenario_type' => $scenario->scenario_type,
                'metrics' => $metrics,
            ];
        }

        // Calculate deltas between scenarios
        if (count($comparison) > 1) {
            $baseline = $comparison[0]['metrics'];
            for ($i = 1; $i < count($comparison); $i++) {
                $current = $comparison[$i]['metrics'];
                $comparison[$i]['delta'] = [
                    'total_weighted' => round($current['total_weighted'] - $baseline['total_weighted'], 2),
                    'total_unweighted' => round($current['total_unweighted'] - $baseline['total_unweighted'], 2),
                    'deal_count' => $current['deal_count'] - $baseline['deal_count'],
                    'average_probability' => round($current['average_probability'] - $baseline['average_probability'], 1),
                ];
            }
        }

        return $comparison;
    }
}
