<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Quotas;

use App\Application\Services\Goal\GoalApplicationService;
use App\Http\Controllers\Controller;
use App\Models\Quota;
use App\Models\QuotaPeriod;
use App\Services\Quotas\LeaderboardService;
use App\Services\Quotas\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotaController extends Controller
{
    public function __construct(
        protected QuotaService $quotaService,
        protected LeaderboardService $leaderboardService,
        protected GoalApplicationService $goalApplicationService
    ) {}

    /**
     * List quotas with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Quota::with(['period', 'user']);

        if ($request->has('period_id')) {
            $query->forPeriod($request->period_id);
        }

        if ($request->has('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->has('metric_type')) {
            $query->metricType($request->metric_type);
        }

        if ($request->boolean('active')) {
            $query->active();
        }

        $quotas = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

        return response()->json($quotas);
    }

    /**
     * Create a new quota.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:quota_periods,id',
            'user_id' => 'required|exists:users,id',
            'metric_type' => 'required|string|in:revenue,deals,leads,calls,meetings,activities,custom',
            'metric_field' => 'nullable|string|max:100',
            'module_api_name' => 'nullable|string|max:100',
            'target_value' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
        ]);

        $quota = $this->quotaService->createQuota($validated, auth()->id());

        return response()->json([
            'data' => $quota->load(['period', 'user']),
            'message' => 'Quota created successfully',
        ], 201);
    }

    /**
     * Show a specific quota.
     */
    public function show(Quota $quota): JsonResponse
    {
        $quota->load(['period', 'user', 'snapshots' => function ($q) {
            $q->orderByDesc('snapshot_date')->limit(30);
        }]);

        return response()->json([
            'data' => $quota,
        ]);
    }

    /**
     * Update a quota.
     */
    public function update(Request $request, Quota $quota): JsonResponse
    {
        $validated = $request->validate([
            'target_value' => 'sometimes|numeric|min:0',
            'current_value' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|size:3',
        ]);

        $quota = $this->quotaService->updateQuota($quota, $validated);

        return response()->json([
            'data' => $quota,
            'message' => 'Quota updated successfully',
        ]);
    }

    /**
     * Delete a quota.
     */
    public function destroy(Quota $quota): JsonResponse
    {
        $quota->delete();

        return response()->json([
            'message' => 'Quota deleted successfully',
        ]);
    }

    /**
     * Bulk create quotas for multiple users.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:quota_periods,id',
            'metric_type' => 'required|string|in:revenue,deals,leads,calls,meetings,activities,custom',
            'target_value' => 'required|numeric|min:0',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $quotas = $this->quotaService->bulkCreateQuotas(
            $validated['period_id'],
            $validated['metric_type'],
            $validated['target_value'],
            $validated['user_ids'],
            auth()->id()
        );

        return response()->json([
            'data' => $quotas,
            'message' => count($quotas) . ' quotas created/updated successfully',
        ], 201);
    }

    /**
     * Get current user's quota progress.
     */
    public function myProgress(): JsonResponse
    {
        $progress = $this->quotaService->getMyProgress(auth()->id());

        return response()->json([
            'data' => $progress,
        ]);
    }

    /**
     * Get team quota progress.
     */
    public function teamProgress(Request $request): JsonResponse
    {
        $progress = $this->quotaService->getTeamProgress($request->get('period_id'));

        return response()->json([
            'data' => $progress,
        ]);
    }

    /**
     * Get leaderboard.
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $leaderboard = $this->leaderboardService->getLeaderboard(
            $request->get('period_id'),
            $request->get('metric_type', Quota::METRIC_REVENUE),
            $request->get('limit', 10)
        );

        return response()->json([
            'data' => $leaderboard,
        ]);
    }

    /**
     * Get current user's leaderboard position.
     */
    public function myPosition(Request $request): JsonResponse
    {
        $position = $this->leaderboardService->getUserPosition(
            auth()->id(),
            $request->get('period_id'),
            $request->get('metric_type', Quota::METRIC_REVENUE)
        );

        return response()->json([
            'data' => $position,
        ]);
    }

    /**
     * Get available metric types.
     */
    public function metricTypes(): JsonResponse
    {
        return response()->json([
            'data' => Quota::getMetricTypes(),
        ]);
    }

    /**
     * Refresh leaderboard.
     */
    public function refreshLeaderboard(Request $request): JsonResponse
    {
        $periodId = $request->get('period_id');

        if (!$periodId) {
            $period = QuotaPeriod::getCurrentPeriod();
            $periodId = $period?->id;
        }

        if (!$periodId) {
            return response()->json([
                'message' => 'No active period found',
            ], 422);
        }

        $this->leaderboardService->refreshLeaderboard($periodId, $request->get('metric_type'));

        return response()->json([
            'message' => 'Leaderboard refreshed successfully',
        ]);
    }

    /**
     * Recalculate quotas for a period.
     */
    public function recalculate(Request $request): JsonResponse
    {
        $periodId = $request->get('period_id');

        if (!$periodId) {
            $period = QuotaPeriod::getCurrentPeriod();
            $periodId = $period?->id;
        }

        if (!$periodId) {
            return response()->json([
                'message' => 'No active period found',
            ], 422);
        }

        $this->quotaService->recalculateQuotasForPeriod($periodId);

        return response()->json([
            'message' => 'Quotas recalculated successfully',
        ]);
    }
}
