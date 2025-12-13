<?php

declare(strict_types=1);

namespace App\Services\Quotas;

use App\Models\Quota;
use App\Models\QuotaPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class QuotaService
{
    /**
     * Create a new quota period.
     */
    public function createPeriod(array $data): QuotaPeriod
    {
        return QuotaPeriod::create([
            'name' => $data['name'],
            'period_type' => $data['period_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Get or create current period for a given type.
     */
    public function getOrCreateCurrentPeriod(string $type = QuotaPeriod::TYPE_QUARTER): QuotaPeriod
    {
        $existing = QuotaPeriod::getCurrentPeriod($type);

        if ($existing) {
            return $existing;
        }

        $today = Carbon::today();

        return match ($type) {
            QuotaPeriod::TYPE_MONTH => QuotaPeriod::createMonthPeriod($today->year, $today->month),
            QuotaPeriod::TYPE_QUARTER => QuotaPeriod::createQuarterPeriod($today->year, $today->quarter),
            QuotaPeriod::TYPE_YEAR => QuotaPeriod::createYearPeriod($today->year),
            default => throw new \InvalidArgumentException("Invalid period type: {$type}"),
        };
    }

    /**
     * Create a quota for a user.
     */
    public function createQuota(array $data, ?int $createdBy = null): Quota
    {
        return Quota::create([
            'period_id' => $data['period_id'],
            'user_id' => $data['user_id'],
            'team_id' => $data['team_id'] ?? null,
            'metric_type' => $data['metric_type'],
            'metric_field' => $data['metric_field'] ?? null,
            'module_api_name' => $data['module_api_name'] ?? null,
            'target_value' => $data['target_value'],
            'currency' => $data['currency'] ?? 'USD',
            'current_value' => $data['current_value'] ?? 0,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Update a quota.
     */
    public function updateQuota(Quota $quota, array $data): Quota
    {
        $quota->update($data);

        if (isset($data['target_value']) || isset($data['current_value'])) {
            $quota->recalculate();
        }

        return $quota->fresh();
    }

    /**
     * Bulk create quotas for multiple users.
     */
    public function bulkCreateQuotas(int $periodId, string $metricType, float $targetValue, array $userIds, ?int $createdBy = null): array
    {
        $quotas = [];

        DB::transaction(function () use ($periodId, $metricType, $targetValue, $userIds, $createdBy, &$quotas) {
            foreach ($userIds as $userId) {
                // Check if quota already exists
                $existing = Quota::where('period_id', $periodId)
                    ->where('user_id', $userId)
                    ->where('metric_type', $metricType)
                    ->first();

                if ($existing) {
                    $existing->update(['target_value' => $targetValue]);
                    $existing->recalculate();
                    $quotas[] = $existing;
                } else {
                    $quotas[] = $this->createQuota([
                        'period_id' => $periodId,
                        'user_id' => $userId,
                        'metric_type' => $metricType,
                        'target_value' => $targetValue,
                    ], $createdBy);
                }
            }
        });

        return $quotas;
    }

    /**
     * Get quotas for a user in a period.
     */
    public function getUserQuotas(int $userId, ?int $periodId = null): Collection
    {
        $query = Quota::with(['period', 'user'])
            ->forUser($userId);

        if ($periodId) {
            $query->forPeriod($periodId);
        } else {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get quota progress for current user.
     */
    public function getMyProgress(?int $userId = null): array
    {
        $userId = $userId ?? auth()->id();

        $quotas = Quota::with(['period', 'snapshots' => function ($q) {
            $q->orderByDesc('snapshot_date')->limit(7);
        }])
            ->forUser($userId)
            ->active()
            ->get();

        return $quotas->map(function ($quota) {
            return [
                'id' => $quota->id,
                'metric_type' => $quota->metric_type,
                'metric_label' => $quota->metric_label,
                'target_value' => $quota->target_value,
                'current_value' => $quota->current_value,
                'attainment_percent' => $quota->attainment_percent,
                'gap_to_target' => $quota->gap_to_target,
                'pace_required' => $quota->pace_required,
                'is_achieved' => $quota->is_achieved,
                'currency' => $quota->currency,
                'period' => [
                    'id' => $quota->period->id,
                    'name' => $quota->period->name,
                    'days_remaining' => $quota->period->days_remaining,
                    'days_total' => $quota->period->days_total,
                    'progress_percent' => $quota->period->progress_percent,
                ],
                'trend' => $quota->snapshots->map(fn($s) => [
                    'date' => $s->snapshot_date->format('M d'),
                    'value' => $s->current_value,
                    'attainment' => $s->attainment_percent,
                ])->reverse()->values(),
            ];
        })->toArray();
    }

    /**
     * Get team quota progress.
     */
    public function getTeamProgress(?int $periodId = null): array
    {
        $period = $periodId
            ? QuotaPeriod::find($periodId)
            : QuotaPeriod::getCurrentPeriod();

        if (!$period) {
            return [];
        }

        $quotas = Quota::with(['user', 'period'])
            ->forPeriod($period->id)
            ->whereNotNull('user_id')
            ->orderByDesc('attainment_percent')
            ->get();

        // Group by user and metric type
        $byUser = $quotas->groupBy('user_id');

        return $byUser->map(function ($userQuotas) {
            $user = $userQuotas->first()->user;
            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar_url ?? null,
                ],
                'quotas' => $userQuotas->map(fn($q) => [
                    'metric_type' => $q->metric_type,
                    'metric_label' => $q->metric_label,
                    'target_value' => $q->target_value,
                    'current_value' => $q->current_value,
                    'attainment_percent' => $q->attainment_percent,
                    'is_achieved' => $q->is_achieved,
                ]),
            ];
        })->values()->toArray();
    }

    /**
     * Recalculate all quotas for a period.
     */
    public function recalculateQuotasForPeriod(int $periodId): void
    {
        $quotas = Quota::forPeriod($periodId)->get();

        foreach ($quotas as $quota) {
            $newValue = $this->calculateMetricValue($quota);
            $quota->updateProgress($newValue);
        }
    }

    /**
     * Calculate the current value for a quota's metric.
     */
    public function calculateMetricValue(Quota $quota): float
    {
        $period = $quota->period;

        return match ($quota->metric_type) {
            Quota::METRIC_REVENUE => $this->calculateRevenue($quota->user_id, $period),
            Quota::METRIC_DEALS => $this->calculateDeals($quota->user_id, $period),
            Quota::METRIC_LEADS => $this->calculateLeads($quota->user_id, $period),
            Quota::METRIC_ACTIVITIES => $this->calculateActivities($quota->user_id, $period),
            Quota::METRIC_CALLS => $this->calculateActivityType($quota->user_id, $period, 'call'),
            Quota::METRIC_MEETINGS => $this->calculateActivityType($quota->user_id, $period, 'meeting'),
            default => $quota->current_value,
        };
    }

    protected function calculateRevenue(int $userId, QuotaPeriod $period): float
    {
        // Sum of closed won deals in the period
        return DB::table('module_records')
            ->join('modules', 'module_records.module_id', '=', 'modules.id')
            ->where('modules.api_name', 'deals')
            ->where('module_records.owner_id', $userId)
            ->whereRaw("(module_records.data->>'stage')::text ILIKE '%won%'")
            ->whereBetween('module_records.updated_at', [$period->start_date, $period->end_date])
            ->sum(DB::raw("COALESCE((module_records.data->>'amount')::numeric, 0)"));
    }

    protected function calculateDeals(int $userId, QuotaPeriod $period): float
    {
        return DB::table('module_records')
            ->join('modules', 'module_records.module_id', '=', 'modules.id')
            ->where('modules.api_name', 'deals')
            ->where('module_records.owner_id', $userId)
            ->whereRaw("(module_records.data->>'stage')::text ILIKE '%won%'")
            ->whereBetween('module_records.updated_at', [$period->start_date, $period->end_date])
            ->count();
    }

    protected function calculateLeads(int $userId, QuotaPeriod $period): float
    {
        return DB::table('module_records')
            ->join('modules', 'module_records.module_id', '=', 'modules.id')
            ->where('modules.api_name', 'leads')
            ->where('module_records.owner_id', $userId)
            ->whereBetween('module_records.created_at', [$period->start_date, $period->end_date])
            ->count();
    }

    protected function calculateActivities(int $userId, QuotaPeriod $period): float
    {
        return DB::table('activities')
            ->where('user_id', $userId)
            ->where('is_completed', true)
            ->whereBetween('completed_at', [$period->start_date, $period->end_date])
            ->count();
    }

    protected function calculateActivityType(int $userId, QuotaPeriod $period, string $type): float
    {
        return DB::table('activities')
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('is_completed', true)
            ->whereBetween('completed_at', [$period->start_date, $period->end_date])
            ->count();
    }

    /**
     * Create daily snapshots for all active quotas.
     */
    public function createDailySnapshots(): int
    {
        $quotas = Quota::active()->get();
        $count = 0;

        foreach ($quotas as $quota) {
            // Check if snapshot already exists for today
            $exists = $quota->snapshots()
                ->where('snapshot_date', now()->toDateString())
                ->exists();

            if (!$exists) {
                $quota->createSnapshot();
                $count++;
            }
        }

        return $count;
    }
}
