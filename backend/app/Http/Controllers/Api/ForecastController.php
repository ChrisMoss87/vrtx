<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\Forecasting\ForecastingApplicationService;
use App\Domain\Forecasting\DTOs\CreateQuotaDTO;
use App\Domain\Forecasting\DTOs\UpdateDealForecastDTO;
use App\Domain\Forecasting\ValueObjects\ForecastCategory;
use App\Domain\Forecasting\ValueObjects\ForecastPeriod;
use App\Domain\Forecasting\ValueObjects\QuotaType;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ForecastController extends Controller
{
    public function __construct(
        protected ForecastingApplicationService $forecastingService
    ) {}

    /**
     * Get forecast summary for a pipeline.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pipeline_id' => 'required|integer|exists:pipelines,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'period_type' => 'nullable|string|in:week,month,quarter,year',
            'period_start' => 'nullable|date',
        ]);

        $forecastDTO = $this->forecastingService->getForecastSummary(
            pipelineId: $validated['pipeline_id'],
            userId: $validated['user_id'] ?? Auth::id(),
            periodType: $validated['period_type'] ?? 'month',
            periodStart: isset($validated['period_start']) ? new DateTimeImmutable($validated['period_start']) : null
        );

        return response()->json([
            'data' => $forecastDTO->toArray(),
        ]);
    }

    /**
     * Get deals with forecast data.
     */
    public function deals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pipeline_id' => 'required|integer|exists:pipelines,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'period_type' => 'nullable|string|in:week,month,quarter,year',
            'period_start' => 'nullable|date',
            'category' => 'nullable|string|in:commit,best_case,pipeline',
        ]);

        $deals = $this->forecastService->getDealsWithForecast(
            $validated['pipeline_id'],
            $validated['user_id'] ?? Auth::id(),
            $validated['period_type'] ?? 'month',
            isset($validated['period_start']) ? Carbon::parse($validated['period_start']) : null,
            $validated['category'] ?? null
        );

        return response()->json([
            'data' => $deals,
        ]);
    }

    /**
     * Update a deal's forecast settings.
     */
    public function updateDeal(Request $request, int $recordId): JsonResponse
    {
        $validated = $request->validate([
            'forecast_category' => 'nullable|string|in:commit,best_case,pipeline,omitted',
            'forecast_override' => 'nullable|numeric|min:0',
            'expected_close_date' => 'nullable|date',
            'reason' => 'nullable|string|max:500',
        ]);

        $dto = new UpdateDealForecastDTO(
            moduleRecordId: $recordId,
            userId: Auth::id(),
            category: isset($validated['forecast_category']) ? ForecastCategory::from($validated['forecast_category']) : null,
            override: isset($validated['forecast_override']) ? (float) $validated['forecast_override'] : null,
            expectedCloseDate: isset($validated['expected_close_date']) ? new DateTimeImmutable($validated['expected_close_date']) : null,
            reason: $validated['reason'] ?? null,
        );

        $updatedDeal = $this->forecastingService->updateDealForecast($dto);

        return response()->json([
            'data' => [
                'id' => $updatedDeal->id,
                'forecast_category' => $updatedDeal->forecast_category,
                'forecast_override' => $updatedDeal->forecast_override,
                'expected_close_date' => $updatedDeal->expected_close_date?->toDateString(),
            ],
            'message' => 'Forecast updated successfully',
        ]);
    }

    /**
     * Get forecast history.
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pipeline_id' => 'required|integer|exists:pipelines,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'period_type' => 'nullable|string|in:week,month,quarter,year',
            'limit' => 'nullable|integer|min:1|max:52',
        ]);

        $history = $this->forecastService->getForecastHistory(
            $validated['pipeline_id'],
            $validated['user_id'] ?? Auth::id(),
            $validated['period_type'] ?? 'month',
            $validated['limit'] ?? 12
        );

        return response()->json([
            'data' => $history,
        ]);
    }

    /**
     * Get forecast accuracy analysis.
     */
    public function accuracy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pipeline_id' => 'required|integer|exists:pipelines,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'period_type' => 'nullable|string|in:week,month,quarter,year',
            'periods' => 'nullable|integer|min:1|max:12',
        ]);

        $accuracy = $this->forecastingService->getForecastAccuracy(
            pipelineId: $validated['pipeline_id'],
            userId: $validated['user_id'] ?? Auth::id(),
            periodType: $validated['period_type'] ?? 'month',
            periods: $validated['periods'] ?? 6
        );

        return response()->json([
            'data' => $accuracy,
        ]);
    }

    /**
     * Get forecast adjustments for a deal.
     */
    public function adjustments(int $recordId): JsonResponse
    {
        $adjustments = ForecastAdjustment::forRecord($recordId)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $adjustments,
        ]);
    }

    /**
     * List quotas.
     */
    public function quotas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'pipeline_id' => 'nullable|integer|exists:pipelines,id',
            'period_type' => 'nullable|string|in:month,quarter,year',
            'current_only' => 'nullable|boolean',
        ]);

        $query = SalesQuota::with(['user:id,name,email', 'pipeline:id,name']);

        if (isset($validated['user_id'])) {
            $query->forUser($validated['user_id']);
        }

        if (isset($validated['pipeline_id'])) {
            $query->where('pipeline_id', $validated['pipeline_id']);
        }

        if (isset($validated['period_type'])) {
            $query->where('period_type', $validated['period_type']);
        }

        if ($validated['current_only'] ?? false) {
            $query->current();
        }

        $quotas = $query->orderBy('period_start', 'desc')->get();

        return response()->json([
            'data' => $quotas,
        ]);
    }

    /**
     * Create a quota.
     */
    public function storeQuota(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'pipeline_id' => 'nullable|integer|exists:pipelines,id',
            'team_id' => 'nullable|integer',
            'period_type' => 'required|string|in:month,quarter,year',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'quota_amount' => 'required|numeric|min:0',
            'quota_type' => 'nullable|string|in:revenue,deals,activities',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string|max:1000',
        ]);

        $period = ForecastPeriod::fromType(
            $validated['period_type'],
            new DateTimeImmutable($validated['period_start'])
        );

        $dto = new CreateQuotaDTO(
            period: $period,
            quotaAmount: (float) $validated['quota_amount'],
            quotaType: isset($validated['quota_type']) ? QuotaType::from($validated['quota_type']) : QuotaType::REVENUE,
            userId: $validated['user_id'] ?? null,
            pipelineId: $validated['pipeline_id'] ?? null,
            teamId: $validated['team_id'] ?? null,
            currency: $validated['currency'] ?? 'USD',
            notes: $validated['notes'] ?? null,
        );

        $quota = $this->forecastingService->saveQuota($dto);

        return response()->json([
            'data' => [
                'id' => $quota->getId(),
                'user_id' => $quota->userId()?->value(),
                'pipeline_id' => $quota->pipelineId(),
                'team_id' => $quota->teamId(),
                'period' => $quota->period()->toArray(),
                'quota_amount' => $quota->quotaAmount(),
                'quota_type' => $quota->quotaType()->value,
                'currency' => $quota->currency(),
                'notes' => $quota->notes(),
            ],
            'message' => 'Quota created successfully',
        ], 201);
    }

    /**
     * Update a quota.
     */
    public function updateQuota(Request $request, int $quotaId): JsonResponse
    {
        $quota = DB::table('sales_quotas')->where('id', $quotaId)->first();

        $validated = $request->validate([
            'quota_amount' => 'sometimes|numeric|min:0',
            'period_end' => 'sometimes|date|after:period_start',
            'notes' => 'nullable|string|max:1000',
        ]);

        $quota->update($validated);
        $quota->load(['user:id,name,email', 'pipeline:id,name']);

        return response()->json([
            'data' => $quota,
            'message' => 'Quota updated successfully',
        ]);
    }

    /**
     * Delete a quota.
     */
    public function destroyQuota(int $quotaId): JsonResponse
    {
        $quota = DB::table('sales_quotas')->where('id', $quotaId)->first();
        $quota->delete();

        return response()->json([
            'message' => 'Quota deleted successfully',
        ]);
    }

    /**
     * Get quota attainment summary.
     */
    public function quotaAttainment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pipeline_id' => 'required|integer|exists:pipelines,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'period_type' => 'nullable|string|in:month,quarter,year',
            'period_start' => 'nullable|date',
        ]);

        $userId = $validated['user_id'] ?? Auth::id();
        $periodType = $validated['period_type'] ?? 'month';
        $periodStart = isset($validated['period_start'])
            ? Carbon::parse($validated['period_start'])
            : null;

        // Get forecast summary
        $summary = $this->forecastService->getForecastSummary(
            $validated['pipeline_id'],
            $userId,
            $periodType,
            $periodStart
        );

        // Get quota
        $quota = $summary['quota'];

        if (!$quota) {
            return response()->json([
                'data' => [
                    'quota' => null,
                    'closed_won' => $summary['closed_won']['amount'],
                    'commit' => $summary['commit']['amount'],
                    'best_case' => $summary['best_case']['amount'],
                    'pipeline' => $summary['pipeline']['amount'],
                    'weighted' => $summary['weighted']['amount'],
                    'attainment' => null,
                    'remaining' => null,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'quota' => $quota['amount'],
                'closed_won' => $summary['closed_won']['amount'],
                'commit' => $summary['commit']['amount'],
                'best_case' => $summary['best_case']['amount'],
                'pipeline' => $summary['pipeline']['amount'],
                'weighted' => $summary['weighted']['amount'],
                'attainment' => $quota['attainment'],
                'remaining' => $quota['remaining'],
            ],
        ]);
    }
}
