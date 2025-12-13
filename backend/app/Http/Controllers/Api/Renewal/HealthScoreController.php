<?php

namespace App\Http\Controllers\Api\Renewal;

use App\Http\Controllers\Controller;
use App\Models\CustomerHealthScore;
use App\Services\Renewal\HealthScoreService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HealthScoreController extends Controller
{
    public function __construct(
        protected HealthScoreService $healthScoreService
    ) {}

    /**
     * List all health scores
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomerHealthScore::with(['history' => fn($q) => $q->latest()->limit(10)])
            ->when($request->status, fn($q, $status) => $q->where('health_status', $status))
            ->when($request->min_score, fn($q, $min) => $q->where('overall_score', '>=', $min))
            ->when($request->max_score, fn($q, $max) => $q->where('overall_score', '<=', $max))
            ->when($request->related_module, fn($q, $module) => $q->where('related_module', $module));

        $scores = $query->orderBy($request->sort_by ?? 'overall_score', $request->sort_order ?? 'asc')
            ->paginate($request->per_page ?? 20);

        return response()->json($scores);
    }

    /**
     * Get health score for a specific record
     */
    public function show(string $module, int $recordId): JsonResponse
    {
        $healthScore = CustomerHealthScore::with(['history' => fn($q) => $q->latest()->limit(30)])
            ->forModule($module, $recordId)
            ->first();

        if (!$healthScore) {
            // Calculate if doesn't exist
            $healthScore = $this->healthScoreService->calculateHealthScore($module, $recordId);
            $healthScore->load(['history' => fn($q) => $q->latest()->limit(30)]);
        }

        return response()->json([
            'health_score' => $healthScore,
        ]);
    }

    /**
     * Calculate/refresh health score for a record
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module' => 'required|string',
            'record_id' => 'required|integer',
        ]);

        $healthScore = $this->healthScoreService->calculateHealthScore(
            $validated['module'],
            $validated['record_id']
        );

        return response()->json([
            'health_score' => $healthScore->load(['history' => fn($q) => $q->latest()->limit(10)]),
            'message' => 'Health score calculated',
        ]);
    }

    /**
     * Get summary statistics
     */
    public function summary(): JsonResponse
    {
        $summary = $this->healthScoreService->getHealthSummary();

        return response()->json($summary);
    }

    /**
     * Get at-risk customers
     */
    public function atRisk(): JsonResponse
    {
        $customers = $this->healthScoreService->getAtRiskCustomers();

        return response()->json([
            'customers' => $customers,
        ]);
    }

    /**
     * Bulk recalculate all health scores
     */
    public function recalculateAll(): JsonResponse
    {
        $count = $this->healthScoreService->recalculateAllScores();

        return response()->json([
            'count' => $count,
            'message' => "Recalculated {$count} health scores",
        ]);
    }

    /**
     * Update notes on a health score
     */
    public function updateNotes(Request $request, int $id): JsonResponse
    {
        $healthScore = CustomerHealthScore::findOrFail($id);

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $healthScore->update($validated);

        return response()->json([
            'health_score' => $healthScore,
            'message' => 'Notes updated',
        ]);
    }
}
