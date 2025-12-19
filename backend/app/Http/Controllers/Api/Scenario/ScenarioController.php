<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Scenario;

use App\Application\Services\Forecasting\ForecastingApplicationService;
use App\Domain\Forecasting\DTOs\CreateForecastScenarioDTO;
use App\Domain\Forecasting\ValueObjects\ScenarioType;
use App\Http\Controllers\Controller;
use App\Models\ForecastScenario;
use App\Services\Scenario\GapAnalysisService;
use App\Services\Scenario\ScenarioCalculatorService;
use App\Services\Scenario\ScenarioService;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScenarioController extends Controller
{
    public function __construct(
        protected ForecastingApplicationService $forecastingService,
        protected ScenarioService $scenarioService,
        protected ScenarioCalculatorService $calculator,
        protected GapAnalysisService $gapAnalysis
    ) {}

    /**
     * List scenarios.
     * GET /api/v1/scenarios
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'scenario_type' => 'nullable|string',
        ]);

        $scenarios = $this->scenarioService->getScenarios(
            auth()->id(),
            $validated
        );

        return response()->json([
            'data' => $scenarios->map(function ($scenario) {
                return [
                    'id' => $scenario->id,
                    'name' => $scenario->name,
                    'description' => $scenario->description,
                    'scenario_type' => $scenario->scenario_type,
                    'period_start' => $scenario->period_start->format('Y-m-d'),
                    'period_end' => $scenario->period_end->format('Y-m-d'),
                    'total_weighted' => $scenario->total_weighted,
                    'total_unweighted' => $scenario->total_unweighted,
                    'target_amount' => $scenario->target_amount,
                    'deal_count' => $scenario->deal_count,
                    'is_shared' => $scenario->is_shared,
                    'is_baseline' => $scenario->is_baseline,
                    'user' => $scenario->user ? [
                        'id' => $scenario->user->id,
                        'name' => $scenario->user->name,
                    ] : null,
                    'created_at' => $scenario->created_at->toISOString(),
                    'updated_at' => $scenario->updated_at->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Get a scenario with deals.
     * GET /api/v1/scenarios/{id}
     */
    public function show(int $id): JsonResponse
    {
        $scenario = $this->scenarioService->getScenario($id);

        if (!$scenario) {
            return response()->json(['message' => 'Scenario not found'], 404);
        }

        $metrics = $this->calculator->calculateMetrics($scenario);

        return response()->json([
            'data' => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'description' => $scenario->description,
                'scenario_type' => $scenario->scenario_type,
                'period_start' => $scenario->period_start->format('Y-m-d'),
                'period_end' => $scenario->period_end->format('Y-m-d'),
                'target_amount' => $scenario->target_amount,
                'is_shared' => $scenario->is_shared,
                'is_baseline' => $scenario->is_baseline,
                'settings' => $scenario->settings,
                'user' => $scenario->user ? [
                    'id' => $scenario->user->id,
                    'name' => $scenario->user->name,
                ] : null,
                'deals' => $scenario->deals->map(function ($deal) {
                    return [
                        'id' => $deal->id,
                        'deal_record_id' => $deal->deal_record_id,
                        'name' => $deal->dealRecord?->data['name'] ?? $deal->dealRecord?->data['deal_name'] ?? 'Unknown',
                        'amount' => $deal->amount,
                        'probability' => $deal->probability,
                        'weighted_amount' => $deal->getWeightedAmount(),
                        'close_date' => $deal->close_date?->format('Y-m-d'),
                        'stage_id' => $deal->stage_id,
                        'stage_name' => $deal->stage?->name,
                        'is_committed' => $deal->is_committed,
                        'is_excluded' => $deal->is_excluded,
                        'has_changes' => $deal->hasChanges(),
                        'changes' => $deal->getChangeSummary(),
                        'notes' => $deal->notes,
                        'original_data' => $deal->original_data,
                    ];
                }),
                'metrics' => $metrics,
                'created_at' => $scenario->created_at->toISOString(),
                'updated_at' => $scenario->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Create a scenario.
     * POST /api/v1/scenarios
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module_id' => 'required|integer|exists:modules,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'scenario_type' => 'nullable|string|in:current,best_case,worst_case,target_hit,custom',
            'target_amount' => 'nullable|numeric|min:0',
            'is_shared' => 'nullable|boolean',
            'settings' => 'nullable|array',
        ]);

        $dto = new CreateForecastScenarioDTO(
            name: $validated['name'],
            userId: auth()->id(),
            moduleId: $validated['module_id'],
            periodStart: new DateTimeImmutable($validated['period_start']),
            periodEnd: new DateTimeImmutable($validated['period_end']),
            scenarioType: isset($validated['scenario_type']) ? ScenarioType::from($validated['scenario_type']) : ScenarioType::CUSTOM,
            description: $validated['description'] ?? null,
            targetAmount: isset($validated['target_amount']) ? (float) $validated['target_amount'] : null,
            isBaseline: false,
            isShared: $validated['is_shared'] ?? false,
            settings: $validated['settings'] ?? [],
        );

        $scenario = $this->forecastingService->createScenario($dto);

        return response()->json([
            'data' => [
                'id' => $scenario->getId(),
                'name' => $scenario->name(),
                'description' => $scenario->description(),
                'module_id' => $scenario->moduleId(),
                'scenario_type' => $scenario->scenarioType()->value,
                'period_start' => $scenario->periodStart()->format('Y-m-d'),
                'period_end' => $scenario->periodEnd()->format('Y-m-d'),
                'target_amount' => $scenario->targetAmount(),
                'is_shared' => $scenario->isShared(),
                'is_baseline' => $scenario->isBaseline(),
            ],
            'message' => 'Scenario created successfully',
        ], 201);
    }

    /**
     * Update a scenario.
     * PUT /api/v1/scenarios/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $scenario = ForecastScenario::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'target_amount' => 'nullable|numeric|min:0',
            'is_shared' => 'nullable|boolean',
            'settings' => 'nullable|array',
        ]);

        $scenario = $this->scenarioService->updateScenario($scenario, $validated);

        return response()->json([
            'data' => $scenario,
            'message' => 'Scenario updated successfully',
        ]);
    }

    /**
     * Delete a scenario.
     * DELETE /api/v1/scenarios/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $scenario = ForecastScenario::findOrFail($id);
        $scenario->delete();

        return response()->json([
            'message' => 'Scenario deleted successfully',
        ]);
    }

    /**
     * Duplicate a scenario.
     * POST /api/v1/scenarios/{id}/duplicate
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $scenario = ForecastScenario::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        $newName = $validated['name'] ?? $scenario->name . ' (Copy)';
        $newScenario = $this->scenarioService->duplicateScenario($scenario, $newName, auth()->id());

        return response()->json([
            'data' => $newScenario,
            'message' => 'Scenario duplicated successfully',
        ], 201);
    }

    /**
     * Get deals in a scenario.
     * GET /api/v1/scenarios/{id}/deals
     */
    public function deals(int $id): JsonResponse
    {
        $scenario = $this->scenarioService->getScenario($id);

        if (!$scenario) {
            return response()->json(['message' => 'Scenario not found'], 404);
        }

        return response()->json([
            'data' => $scenario->deals->map(function ($deal) {
                return [
                    'id' => $deal->id,
                    'deal_record_id' => $deal->deal_record_id,
                    'name' => $deal->dealRecord?->data['name'] ?? 'Unknown',
                    'amount' => $deal->amount,
                    'probability' => $deal->probability,
                    'weighted_amount' => $deal->getWeightedAmount(),
                    'close_date' => $deal->close_date?->format('Y-m-d'),
                    'stage_id' => $deal->stage_id,
                    'stage_name' => $deal->stage?->name,
                    'is_committed' => $deal->is_committed,
                    'is_excluded' => $deal->is_excluded,
                    'has_changes' => $deal->hasChanges(),
                    'original_data' => $deal->original_data,
                ];
            }),
        ]);
    }

    /**
     * Update a deal in a scenario.
     * PUT /api/v1/scenarios/{id}/deals/{dealId}
     */
    public function updateDeal(Request $request, int $id, int $dealId): JsonResponse
    {
        $scenario = ForecastScenario::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
            'close_date' => 'nullable|date',
            'stage_id' => 'nullable|integer|exists:stages,id',
            'is_committed' => 'nullable|boolean',
            'is_excluded' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $deal = $this->scenarioService->updateScenarioDeal($scenario, $dealId, $validated);
        $metrics = $this->calculator->calculateMetrics($scenario->fresh());

        return response()->json([
            'data' => [
                'deal' => [
                    'id' => $deal->id,
                    'deal_record_id' => $deal->deal_record_id,
                    'amount' => $deal->amount,
                    'probability' => $deal->probability,
                    'weighted_amount' => $deal->getWeightedAmount(),
                    'close_date' => $deal->close_date?->format('Y-m-d'),
                    'stage_id' => $deal->stage_id,
                    'is_committed' => $deal->is_committed,
                    'has_changes' => $deal->hasChanges(),
                ],
                'scenario_totals' => [
                    'total_weighted' => $metrics['total_weighted'],
                    'total_unweighted' => $metrics['total_unweighted'],
                    'gap_amount' => $metrics['gap_amount'],
                    'progress_percent' => $metrics['progress_percent'],
                ],
            ],
            'message' => 'Deal updated successfully',
        ]);
    }

    /**
     * Commit a deal in a scenario.
     * POST /api/v1/scenarios/{id}/commit/{dealId}
     */
    public function commitDeal(int $id, int $dealId): JsonResponse
    {
        $scenario = ForecastScenario::findOrFail($id);
        $deal = $this->scenarioService->commitDeal($scenario, $dealId);

        return response()->json([
            'data' => $deal,
            'message' => 'Deal committed successfully',
        ]);
    }

    /**
     * Reset a deal to original values.
     * POST /api/v1/scenarios/{id}/reset/{dealId}
     */
    public function resetDeal(int $id, int $dealId): JsonResponse
    {
        $scenario = ForecastScenario::findOrFail($id);
        $deal = $this->scenarioService->resetDeal($scenario, $dealId);

        return response()->json([
            'data' => $deal,
            'message' => 'Deal reset successfully',
        ]);
    }

    /**
     * Compare multiple scenarios.
     * GET /api/v1/scenarios/compare
     */
    public function compare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|string',
        ]);

        $ids = array_map('intval', explode(',', $validated['ids']));
        $comparison = $this->calculator->compareScenarios($ids);

        return response()->json([
            'data' => $comparison,
        ]);
    }

    /**
     * Get gap analysis.
     * GET /api/v1/scenarios/gap-analysis
     */
    public function gapAnalysis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'required|numeric|min:0',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
        ]);

        $analysis = $this->gapAnalysis->analyzeGap(
            (float) $validated['target'],
            $validated['period_start'],
            $validated['period_end'],
            auth()->id()
        );

        return response()->json([
            'data' => $analysis,
        ]);
    }

    /**
     * Auto-generate a scenario.
     * POST /api/v1/scenarios/auto-generate
     */
    public function autoGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:current,best_case,worst_case,target_hit',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
            'target_amount' => 'nullable|numeric|min:0',
        ]);

        $scenario = $this->scenarioService->autoGenerateScenario(
            auth()->id(),
            $validated['type'],
            $validated['period_start'],
            $validated['period_end'],
            $validated['target_amount'] ?? null
        );

        return response()->json([
            'data' => $scenario,
            'message' => 'Scenario generated successfully',
        ], 201);
    }

    /**
     * Get scenario types.
     * GET /api/v1/scenarios/types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => ForecastScenario::getScenarioTypes(),
        ]);
    }
}
