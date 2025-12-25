<?php

declare(strict_types=1);

namespace App\Services\Scenario;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScenarioService
{
    public function __construct(
        protected ScenarioCalculatorService $calculator
    ) {}

    /**
     * Get scenarios for a user.
     */
    public function getScenarios(int $userId, array $filters = []): Collection
    {
        $query = ForecastScenario::forUser($userId)
            ->with(['user:id,name', 'deals'])
            ->orderBy('updated_at', 'desc');

        if (!empty($filters['period_start']) && !empty($filters['period_end'])) {
            $query->forPeriod($filters['period_start'], $filters['period_end']);
        }

        if (!empty($filters['scenario_type'])) {
            $query->where('scenario_type', $filters['scenario_type']);
        }

        return $query->get();
    }

    /**
     * Get a scenario with its deals.
     */
    public function getScenario(int $id): ?ForecastScenario
    {
        return ForecastScenario::with([
            'deals.stage',
            'deals.dealRecord',
            'user:id,name',
        ])->find($id);
    }

    /**
     * Create a new scenario.
     */
    public function createScenario(array $data, int $userId): ForecastScenario
    {
        return DB::transaction(function () use ($data, $userId) {
            $scenario = DB::table('forecast_scenarios')->insertGetId([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'user_id' => $userId,
                'module_id' => $data['module_id'] ?? null,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'scenario_type' => $data['scenario_type'] ?? ForecastScenario::TYPE_CUSTOM,
                'is_baseline' => $data['is_baseline'] ?? false,
                'is_shared' => $data['is_shared'] ?? false,
                'target_amount' => $data['target_amount'] ?? null,
                'settings' => $data['settings'] ?? [],
            ]);

            // Populate with deals from the period
            $this->populateScenarioDeals($scenario, $data);

            return $scenario->fresh(['deals.stage', 'deals.dealRecord']);
        });
    }

    /**
     * Populate scenario with deals from the pipeline.
     */
    protected function populateScenarioDeals(ForecastScenario $scenario, array $options = []): void
    {
        // Find deals module
        $dealsModule = DB::table('modules')->where('api_name', 'deals')
            ->orWhere('api_name', 'opportunities')
            ->first();

        if (!$dealsModule) {
            return;
        }

        // Get deals in the period
        $deals = DB::table('module_records')->where('module_id', $dealsModule->id)
            ->whereJsonContainsKey('data->close_date')
            ->get()
            ->filter(function ($deal) use ($scenario) {
                $closeDate = $deal->data['close_date'] ?? null;
                if (!$closeDate) {
                    return false;
                }
                return $closeDate >= $scenario->period_start->format('Y-m-d')
                    && $closeDate <= $scenario->period_end->format('Y-m-d');
            });

        foreach ($deals as $deal) {
            $amount = (float) ($deal->data['amount'] ?? $deal->data['value'] ?? 0);
            $probability = (int) ($deal->data['probability'] ?? 50);
            $closeDate = $deal->data['close_date'] ?? null;
            $stageId = $deal->data['stage_id'] ?? null;

            DB::table('scenario_deals')->insertGetId([
                'scenario_id' => $scenario->id,
                'deal_record_id' => $deal->id,
                'stage_id' => $stageId,
                'amount' => $amount,
                'probability' => $probability,
                'close_date' => $closeDate,
                'original_data' => [
                    'amount' => $amount,
                    'probability' => $probability,
                    'close_date' => $closeDate,
                    'stage_id' => $stageId,
                ],
            ]);
        }

        $scenario->recalculateTotals();
    }

    /**
     * Update a scenario.
     */
    public function updateScenario(ForecastScenario $scenario, array $data): ForecastScenario
    {
        $scenario->update([
            'name' => $data['name'] ?? $scenario->name,
            'description' => $data['description'] ?? $scenario->description,
            'period_start' => $data['period_start'] ?? $scenario->period_start,
            'period_end' => $data['period_end'] ?? $scenario->period_end,
            'is_shared' => $data['is_shared'] ?? $scenario->is_shared,
            'target_amount' => $data['target_amount'] ?? $scenario->target_amount,
            'settings' => $data['settings'] ?? $scenario->settings,
        ]);

        return $scenario;
    }

    /**
     * Duplicate a scenario.
     */
    public function duplicateScenario(ForecastScenario $scenario, string $newName, int $userId): ForecastScenario
    {
        return DB::transaction(function () use ($scenario, $newName, $userId) {
            $newScenario = $scenario->replicate();
            $newScenario->name = $newName;
            $newScenario->user_id = $userId;
            $newScenario->is_baseline = false;
            $newScenario->save();

            // Copy deals
            foreach ($scenario->deals as $deal) {
                $newDeal = $deal->replicate();
                $newDeal->scenario_id = $newScenario->id;
                $newDeal->save();
            }

            return $newScenario->fresh(['deals']);
        });
    }

    /**
     * Update a deal in a scenario.
     */
    public function updateScenarioDeal(
        ForecastScenario $scenario,
        int $dealId,
        array $data
    ): ScenarioDeal {
        $deal = $scenario->deals()->where('deal_record_id', $dealId)->firstOrFail();

        $deal->update([
            'amount' => $data['amount'] ?? $deal->amount,
            'probability' => $data['probability'] ?? $deal->probability,
            'close_date' => $data['close_date'] ?? $deal->close_date,
            'stage_id' => $data['stage_id'] ?? $deal->stage_id,
            'is_committed' => $data['is_committed'] ?? $deal->is_committed,
            'is_excluded' => $data['is_excluded'] ?? $deal->is_excluded,
            'notes' => $data['notes'] ?? $deal->notes,
        ]);

        $scenario->recalculateTotals();

        return $deal;
    }

    /**
     * Commit a deal in a scenario.
     */
    public function commitDeal(ForecastScenario $scenario, int $dealId): ScenarioDeal
    {
        $deal = $scenario->deals()->where('deal_record_id', $dealId)->firstOrFail();
        $deal->update(['is_committed' => true, 'probability' => 100]);
        $scenario->recalculateTotals();

        return $deal;
    }

    /**
     * Reset a deal to its original values.
     */
    public function resetDeal(ForecastScenario $scenario, int $dealId): ScenarioDeal
    {
        $deal = $scenario->deals()->where('deal_record_id', $dealId)->firstOrFail();
        $deal->resetToOriginal();
        $deal->save();
        $scenario->recalculateTotals();

        return $deal;
    }

    /**
     * Auto-generate a scenario of a specific type.
     */
    public function autoGenerateScenario(
        int $userId,
        string $type,
        string $periodStart,
        string $periodEnd,
        ?float $targetAmount = null
    ): ForecastScenario {
        return DB::transaction(function () use ($userId, $type, $periodStart, $periodEnd, $targetAmount) {
            $scenario = $this->createScenario([
                'name' => ForecastScenario::getScenarioTypes()[$type] . ' - ' . now()->format('M Y'),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'scenario_type' => $type,
                'target_amount' => $targetAmount,
            ], $userId);

            // Apply type-specific adjustments
            switch ($type) {
                case ForecastScenario::TYPE_BEST_CASE:
                    $this->applyBestCaseAdjustments($scenario);
                    break;
                case ForecastScenario::TYPE_WORST_CASE:
                    $this->applyWorstCaseAdjustments($scenario);
                    break;
                case ForecastScenario::TYPE_TARGET_HIT:
                    if ($targetAmount) {
                        $this->applyTargetHitAdjustments($scenario, $targetAmount);
                    }
                    break;
            }

            return $scenario->fresh(['deals.stage']);
        });
    }

    protected function applyBestCaseAdjustments(ForecastScenario $scenario): void
    {
        foreach ($scenario->deals as $deal) {
            // Increase probability by 20% for best case
            $newProb = min(100, ($deal->probability ?? 50) * 1.2);
            $deal->update(['probability' => (int) $newProb]);
        }
        $scenario->recalculateTotals();
    }

    protected function applyWorstCaseAdjustments(ForecastScenario $scenario): void
    {
        foreach ($scenario->deals as $deal) {
            // Decrease probability by 30% for worst case
            $newProb = max(5, ($deal->probability ?? 50) * 0.7);
            $deal->update(['probability' => (int) $newProb]);
        }
        $scenario->recalculateTotals();
    }

    protected function applyTargetHitAdjustments(ForecastScenario $scenario, float $target): void
    {
        // Calculate what probability multiplier is needed to hit target
        $currentWeighted = (float) $scenario->total_weighted;
        if ($currentWeighted == 0) {
            return;
        }

        $multiplier = $target / $currentWeighted;

        foreach ($scenario->deals as $deal) {
            $newProb = min(100, ($deal->probability ?? 50) * $multiplier);
            $deal->update(['probability' => (int) $newProb]);
        }
        $scenario->recalculateTotals();
    }
}
