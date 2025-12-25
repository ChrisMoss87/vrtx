<?php

declare(strict_types=1);

namespace App\Services\Quotas;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    /**
     * Get leaderboard for a period and metric type.
     */
    public function getLeaderboard(?int $periodId = null, string $metricType = Quota::METRIC_REVENUE, int $limit = 10): array
    {
        $period = $periodId
            ? DB::table('quota_periods')->where('id', $periodId)->first()
            : QuotaPeriod::getCurrentPeriod();

        if (!$period) {
            return [];
        }

        $entries = LeaderboardEntry::with('user')
            ->where('period_id', $period->id)
            ->where('metric_type', $metricType)
            ->orderBy('rank')
            ->limit($limit)
            ->get();

        return [
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'days_remaining' => $period->days_remaining,
            ],
            'metric_type' => $metricType,
            'entries' => $entries->map(fn($entry) => [
                'rank' => $entry->rank,
                'rank_badge' => $entry->rank_badge,
                'user' => [
                    'id' => $entry->user->id,
                    'name' => $entry->user->name,
                    'avatar' => $entry->user->avatar_url ?? null,
                ],
                'value' => $entry->value,
                'target' => $entry->target,
                'attainment_percent' => $entry->attainment_percent,
                'gap' => $entry->gap,
                'trend' => $entry->trend,
            ]),
        ];
    }

    /**
     * Refresh leaderboard for a period.
     */
    public function refreshLeaderboard(int $periodId, ?string $metricType = null): void
    {
        $period = DB::table('quota_periods')->where('id', $periodId)->first();
        $metricTypes = $metricType ? [$metricType] : array_keys(Quota::getMetricTypes());

        foreach ($metricTypes as $type) {
            $this->refreshLeaderboardForMetric($period, $type);
        }
    }

    /**
     * Refresh leaderboard for a specific metric type.
     */
    protected function refreshLeaderboardForMetric(QuotaPeriod $period, string $metricType): void
    {
        // Get all quotas for this period and metric type
        $quotas = Quota::with('user')
            ->forPeriod($period->id)
            ->metricType($metricType)
            ->whereNotNull('user_id')
            ->orderByDesc('attainment_percent')
            ->get();

        // Calculate trends (compare to previous week)
        $previousWeekValues = $this->getPreviousWeekValues($quotas);

        DB::transaction(function () use ($period, $metricType, $quotas, $previousWeekValues) {
            // Delete existing entries
            DB::table('leaderboard_entries')->where('period_id', $period->id)
                ->where('metric_type', $metricType)
                ->delete();

            // Create new entries
            $rank = 1;
            foreach ($quotas as $quota) {
                $previousValue = $previousWeekValues[$quota->id] ?? $quota->current_value;
                $trend = $previousValue > 0
                    ? round((($quota->current_value - $previousValue) / $previousValue) * 100, 2)
                    : 0;

                DB::table('leaderboard_entries')->insertGetId([
                    'period_id' => $period->id,
                    'metric_type' => $metricType,
                    'user_id' => $quota->user_id,
                    'rank' => $rank++,
                    'value' => $quota->current_value,
                    'target' => $quota->target_value,
                    'attainment_percent' => $quota->attainment_percent,
                    'trend' => $trend,
                ]);
            }
        });
    }

    /**
     * Get previous week's values for trend calculation.
     */
    protected function getPreviousWeekValues(Collection $quotas): array
    {
        $quotaIds = $quotas->pluck('id')->toArray();
        $oneWeekAgo = now()->subWeek()->toDateString();

        $snapshots = DB::table('quota_snapshots')
            ->whereIn('quota_id', $quotaIds)
            ->where('snapshot_date', '<=', $oneWeekAgo)
            ->orderByDesc('snapshot_date')
            ->get()
            ->unique('quota_id');

        return $snapshots->pluck('current_value', 'quota_id')->toArray();
    }

    /**
     * Get user's position on leaderboard.
     */
    public function getUserPosition(int $userId, ?int $periodId = null, string $metricType = Quota::METRIC_REVENUE): ?array
    {
        $period = $periodId
            ? DB::table('quota_periods')->where('id', $periodId)->first()
            : QuotaPeriod::getCurrentPeriod();

        if (!$period) {
            return null;
        }

        $entry = LeaderboardEntry::with('user')
            ->where('period_id', $period->id)
            ->where('metric_type', $metricType)
            ->where('user_id', $userId)
            ->first();

        if (!$entry) {
            return null;
        }

        $totalEntries = DB::table('leaderboard_entries')->where('period_id', $period->id)
            ->where('metric_type', $metricType)
            ->count();

        return [
            'rank' => $entry->rank,
            'rank_badge' => $entry->rank_badge,
            'total' => $totalEntries,
            'percentile' => round((($totalEntries - $entry->rank + 1) / $totalEntries) * 100, 1),
            'value' => $entry->value,
            'target' => $entry->target,
            'attainment_percent' => $entry->attainment_percent,
            'gap' => $entry->gap,
            'trend' => $entry->trend,
        ];
    }

    /**
     * Get available metric types with counts.
     */
    public function getAvailableMetrics(?int $periodId = null): array
    {
        $period = $periodId
            ? DB::table('quota_periods')->where('id', $periodId)->first()
            : QuotaPeriod::getCurrentPeriod();

        if (!$period) {
            return [];
        }

        $metrics = Quota::forPeriod($period->id)
            ->select('metric_type', DB::raw('COUNT(*) as count'))
            ->groupBy('metric_type')
            ->pluck('count', 'metric_type')
            ->toArray();

        $allMetrics = Quota::getMetricTypes();

        return collect($allMetrics)->map(fn($label, $key) => [
            'key' => $key,
            'label' => $label,
            'count' => $metrics[$key] ?? 0,
        ])->values()->toArray();
    }
}
