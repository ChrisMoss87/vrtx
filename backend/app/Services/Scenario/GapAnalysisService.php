<?php

declare(strict_types=1);

namespace App\Services\Scenario;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GapAnalysisService
{
    /**
     * Analyze what's needed to hit a target.
     */
    public function analyzeGap(
        float $targetAmount,
        string $periodStart,
        string $periodEnd,
        ?int $userId = null
    ): array {
        // Get current weighted pipeline for period
        $currentWeighted = $this->getCurrentWeightedPipeline($periodStart, $periodEnd);
        $gap = $targetAmount - $currentWeighted['total_weighted'];

        // Calculate various ways to close the gap
        $recommendations = [];

        if ($gap > 0) {
            // Option 1: Increase win rates
            $winRateIncrease = $this->calculateWinRateIncrease($currentWeighted, $gap);
            if ($winRateIncrease) {
                $recommendations[] = $winRateIncrease;
            }

            // Option 2: Add new deals
            $newDealsNeeded = $this->calculateNewDealsNeeded($currentWeighted, $gap);
            if ($newDealsNeeded) {
                $recommendations[] = $newDealsNeeded;
            }

            // Option 3: Accelerate deals
            $accelerationStrategy = $this->calculateAccelerationStrategy($currentWeighted, $gap, $periodEnd);
            if ($accelerationStrategy) {
                $recommendations[] = $accelerationStrategy;
            }

            // Option 4: Upsell existing deals
            $upsellStrategy = $this->calculateUpsellStrategy($currentWeighted, $gap);
            if ($upsellStrategy) {
                $recommendations[] = $upsellStrategy;
            }
        }

        return [
            'target' => $targetAmount,
            'current_weighted' => $currentWeighted['total_weighted'],
            'current_unweighted' => $currentWeighted['total_unweighted'],
            'gap' => max(0, $gap),
            'gap_percent' => $targetAmount > 0 ? round(($gap / $targetAmount) * 100, 1) : 0,
            'is_on_track' => $gap <= 0,
            'deal_count' => $currentWeighted['deal_count'],
            'average_deal_size' => $currentWeighted['average_deal_size'],
            'average_probability' => $currentWeighted['average_probability'],
            'recommendations' => $recommendations,
            'top_deals' => $currentWeighted['top_deals'],
        ];
    }

    /**
     * Get current weighted pipeline for a period.
     */
    protected function getCurrentWeightedPipeline(string $periodStart, string $periodEnd): array
    {
        $dealsModule = DB::table('modules')->where('api_name', 'deals')
            ->orWhere('api_name', 'opportunities')
            ->first();

        if (!$dealsModule) {
            return [
                'total_weighted' => 0,
                'total_unweighted' => 0,
                'deal_count' => 0,
                'average_deal_size' => 0,
                'average_probability' => 0,
                'top_deals' => [],
            ];
        }

        $deals = DB::table('module_records')->where('module_id', $dealsModule->id)
            ->get()
            ->filter(function ($deal) use ($periodStart, $periodEnd) {
                $closeDate = $deal->data['close_date'] ?? null;
                return $closeDate && $closeDate >= $periodStart && $closeDate <= $periodEnd;
            });

        $totalUnweighted = 0;
        $totalWeighted = 0;
        $probSum = 0;
        $dealList = [];

        foreach ($deals as $deal) {
            $amount = (float) ($deal->data['amount'] ?? $deal->data['value'] ?? 0);
            $probability = (int) ($deal->data['probability'] ?? 50);
            $weighted = $amount * ($probability / 100);

            $totalUnweighted += $amount;
            $totalWeighted += $weighted;
            $probSum += $probability;

            $dealList[] = [
                'id' => $deal->id,
                'name' => $deal->data['name'] ?? $deal->data['deal_name'] ?? 'Unnamed',
                'amount' => $amount,
                'probability' => $probability,
                'weighted' => $weighted,
                'close_date' => $deal->data['close_date'] ?? null,
                'stage' => $deal->data['stage'] ?? null,
            ];
        }

        // Sort by weighted amount desc
        usort($dealList, fn($a, $b) => $b['weighted'] <=> $a['weighted']);

        return [
            'total_weighted' => round($totalWeighted, 2),
            'total_unweighted' => round($totalUnweighted, 2),
            'deal_count' => $deals->count(),
            'average_deal_size' => $deals->count() > 0 ? round($totalUnweighted / $deals->count(), 2) : 0,
            'average_probability' => $deals->count() > 0 ? round($probSum / $deals->count(), 1) : 0,
            'top_deals' => array_slice($dealList, 0, 10),
        ];
    }

    /**
     * Calculate win rate increase needed to close gap.
     */
    protected function calculateWinRateIncrease(array $current, float $gap): ?array
    {
        if ($current['total_unweighted'] == 0) {
            return null;
        }

        $currentWinRate = $current['average_probability'];
        $targetWeighted = $current['total_weighted'] + $gap;
        $neededWinRate = ($targetWeighted / $current['total_unweighted']) * 100;

        if ($neededWinRate > 100) {
            return null; // Impossible with current pipeline
        }

        return [
            'type' => 'increase_win_rate',
            'title' => 'Increase Win Rates',
            'description' => sprintf(
                'Increase average win rate from %.0f%% to %.0f%% (+%.0f%%)',
                $currentWinRate,
                $neededWinRate,
                $neededWinRate - $currentWinRate
            ),
            'current_value' => $currentWinRate,
            'target_value' => round($neededWinRate, 1),
            'increase_needed' => round($neededWinRate - $currentWinRate, 1),
            'feasibility' => $neededWinRate <= 70 ? 'high' : ($neededWinRate <= 85 ? 'medium' : 'low'),
        ];
    }

    /**
     * Calculate new deals needed to close gap.
     */
    protected function calculateNewDealsNeeded(array $current, float $gap): ?array
    {
        $avgDealSize = $current['average_deal_size'] > 0 ? $current['average_deal_size'] : 50000;
        $avgProbability = $current['average_probability'] > 0 ? $current['average_probability'] : 50;
        $avgWeightedPerDeal = $avgDealSize * ($avgProbability / 100);

        if ($avgWeightedPerDeal == 0) {
            return null;
        }

        $dealsNeeded = ceil($gap / $avgWeightedPerDeal);
        $pipelineNeeded = $dealsNeeded * $avgDealSize;

        return [
            'type' => 'add_deals',
            'title' => 'Add New Pipeline',
            'description' => sprintf(
                'Add %d new deals worth $%s total (at average deal size of $%s)',
                $dealsNeeded,
                number_format($pipelineNeeded),
                number_format($avgDealSize)
            ),
            'deals_needed' => $dealsNeeded,
            'pipeline_needed' => round($pipelineNeeded, 2),
            'average_deal_size' => $avgDealSize,
            'feasibility' => $dealsNeeded <= 5 ? 'high' : ($dealsNeeded <= 15 ? 'medium' : 'low'),
        ];
    }

    /**
     * Calculate deal acceleration strategy.
     */
    protected function calculateAccelerationStrategy(array $current, float $gap, string $periodEnd): ?array
    {
        // Look at deals closing after period that could be accelerated
        // This is a simplified version - real implementation would query future deals

        return [
            'type' => 'accelerate_deals',
            'title' => 'Accelerate Future Deals',
            'description' => sprintf(
                'Pull forward deals closing after %s into the current period',
                date('M j', strtotime($periodEnd))
            ),
            'amount_to_accelerate' => round($gap, 2),
            'feasibility' => 'medium',
        ];
    }

    /**
     * Calculate upsell strategy.
     */
    protected function calculateUpsellStrategy(array $current, float $gap): ?array
    {
        if ($current['deal_count'] == 0) {
            return null;
        }

        $avgIncreasePerDeal = $gap / $current['deal_count'];
        $percentIncrease = $current['average_deal_size'] > 0
            ? ($avgIncreasePerDeal / $current['average_deal_size']) * 100
            : 0;

        return [
            'type' => 'upsell',
            'title' => 'Upsell Existing Deals',
            'description' => sprintf(
                'Increase average deal size by $%s (%.0f%% increase per deal)',
                number_format($avgIncreasePerDeal),
                $percentIncrease
            ),
            'increase_per_deal' => round($avgIncreasePerDeal, 2),
            'percent_increase' => round($percentIncrease, 1),
            'feasibility' => $percentIncrease <= 15 ? 'high' : ($percentIncrease <= 30 ? 'medium' : 'low'),
        ];
    }
}
