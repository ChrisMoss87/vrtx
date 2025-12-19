<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\Services;

use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Forecasting\ValueObjects\ProbabilityCategory;
use Illuminate\Support\Collection;

/**
 * ForecastCalculatorService.
 *
 * Domain service responsible for calculating forecast amounts and metrics.
 */
final class ForecastCalculatorService
{
    /**
     * Calculate forecast summary from deals.
     *
     * @param Collection $deals Collection of deal records
     * @param array<int, array{probability: int, is_won: bool, is_lost: bool}> $stageData Stage probability data
     * @param string $valueField Field name containing deal value
     * @param string $stageFieldName Field name containing stage ID
     * @return array{
     *     commit: array{amount: float, count: int, deals: Collection},
     *     best_case: array{amount: float, count: int, deals: Collection},
     *     pipeline: array{amount: float, count: int, deals: Collection},
     *     weighted: array{amount: float, count: int},
     *     closed_won: array{amount: float, count: int, deals: Collection}
     * }
     */
    public function calculateForecastSummary(
        Collection $deals,
        array $stageData,
        string $valueField,
        string $stageFieldName
    ): array {
        $commit = collect();
        $bestCase = collect();
        $pipelineDeals = collect();
        $closedWon = collect();

        $wonStageIds = collect($stageData)->filter(fn($s) => $s['is_won'])->keys()->toArray();
        $lostStageIds = collect($stageData)->filter(fn($s) => $s['is_lost'])->keys()->toArray();

        foreach ($deals as $deal) {
            $stageId = $deal->data[$stageFieldName] ?? null;

            // Skip lost deals
            if ($stageId && in_array($stageId, $lostStageIds, true)) {
                continue;
            }

            // Handle won deals
            if ($stageId && in_array($stageId, $wonStageIds, true)) {
                $closedWon->push($deal);
                continue;
            }

            // Skip omitted deals
            if ($this->getDealCategory($deal) === ProbabilityCategory::OMITTED) {
                continue;
            }

            // Categorize by forecast category
            match ($this->getDealCategory($deal)) {
                ProbabilityCategory::COMMIT => $commit->push($deal),
                ProbabilityCategory::BEST_CASE => $bestCase->push($deal),
                default => $pipelineDeals->push($deal),
            };
        }

        // Calculate amounts
        $getDealAmount = fn($deal) => $deal->forecast_override ?? ($deal->data[$valueField] ?? 0);

        $commitAmount = $commit->sum($getDealAmount);
        $bestCaseAmount = $bestCase->sum($getDealAmount);
        $pipelineAmount = $pipelineDeals->sum($getDealAmount);
        $closedWonAmount = $closedWon->sum($getDealAmount);

        // Calculate weighted forecast
        $weightedAmount = $this->calculateWeightedAmount(
            collect([$commit, $bestCase, $pipelineDeals])->flatten(),
            $stageData,
            $stageFieldName,
            $getDealAmount
        );

        return [
            'commit' => [
                'amount' => round($commitAmount, 2),
                'count' => $commit->count(),
                'deals' => $commit,
            ],
            'best_case' => [
                'amount' => round($bestCaseAmount, 2),
                'count' => $bestCase->count(),
                'deals' => $bestCase,
            ],
            'pipeline' => [
                'amount' => round($pipelineAmount, 2),
                'count' => $pipelineDeals->count(),
                'deals' => $pipelineDeals,
            ],
            'weighted' => [
                'amount' => round($weightedAmount, 2),
                'count' => $commit->count() + $bestCase->count() + $pipelineDeals->count(),
            ],
            'closed_won' => [
                'amount' => round($closedWonAmount, 2),
                'count' => $closedWon->count(),
                'deals' => $closedWon,
            ],
        ];
    }

    /**
     * Calculate weighted forecast amount.
     */
    public function calculateWeightedAmount(
        Collection $deals,
        array $stageData,
        string $stageFieldName,
        callable $getAmountFn
    ): float {
        $weighted = 0.0;

        foreach ($deals as $deal) {
            $stageId = $deal->data[$stageFieldName] ?? null;
            $probability = $stageId && isset($stageData[$stageId])
                ? $stageData[$stageId]['probability'] / 100
                : 0.5;

            $amount = $getAmountFn($deal);
            $weighted += $amount * $probability;
        }

        return $weighted;
    }

    /**
     * Calculate quota attainment.
     */
    public function calculateQuotaAttainment(
        float $actualAmount,
        float $quotaAmount
    ): float {
        if ($quotaAmount <= 0) {
            return 0.0;
        }

        return round(($actualAmount / $quotaAmount) * 100, 1);
    }

    /**
     * Calculate forecast accuracy.
     */
    public function calculateAccuracy(
        float $forecastedAmount,
        float $actualAmount
    ): ?float {
        if ($forecastedAmount <= 0) {
            return null;
        }

        return round(($actualAmount / $forecastedAmount) * 100, 1);
    }

    /**
     * Calculate variance.
     */
    public function calculateVariance(
        float $forecastedAmount,
        float $actualAmount
    ): float {
        return $actualAmount - $forecastedAmount;
    }

    /**
     * Calculate variance percentage.
     */
    public function calculateVariancePercent(
        float $forecastedAmount,
        float $actualAmount
    ): ?float {
        if ($forecastedAmount <= 0) {
            return null;
        }

        return round((($actualAmount - $forecastedAmount) / $forecastedAmount) * 100, 1);
    }

    /**
     * Get deal's forecast category.
     */
    private function getDealCategory($deal): ProbabilityCategory
    {
        $category = $deal->forecast_category ?? 'pipeline';

        return ProbabilityCategory::tryFrom($category) ?? ProbabilityCategory::PIPELINE;
    }
}
