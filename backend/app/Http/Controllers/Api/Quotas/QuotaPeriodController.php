<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Quotas;

use App\Application\Services\Goal\GoalApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Quotas\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotaPeriodController extends Controller
{
    public function __construct(
        protected QuotaService $quotaService,
        protected GoalApplicationService $goalApplicationService
    ) {}

    /**
     * List quota periods.
     */
    public function index(Request $request): JsonResponse
    {
        $query = QuotaPeriod::withCount('quotas');

        if ($request->has('type')) {
            $query->type($request->type);
        }

        if ($request->boolean('active')) {
            $query->active();
        }

        if ($request->boolean('current')) {
            $query->current();
        }

        $periods = $query->orderByDesc('start_date')->paginate($request->get('per_page', 20));

        return response()->json($periods);
    }

    /**
     * Create a new quota period.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'period_type' => 'required|string|in:month,quarter,year,custom',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'nullable|boolean',
        ]);

        $period = $this->quotaService->createPeriod($validated);

        return response()->json([
            'data' => $period,
            'message' => 'Period created successfully',
        ], 201);
    }

    /**
     * Show a specific period.
     */
    public function show(QuotaPeriod $quotaPeriod): JsonResponse
    {
        $quotaPeriod->loadCount('quotas');

        return response()->json([
            'data' => array_merge($quotaPeriod->toArray(), [
                'days_remaining' => $quotaPeriod->days_remaining,
                'days_total' => $quotaPeriod->days_total,
                'days_elapsed' => $quotaPeriod->days_elapsed,
                'progress_percent' => $quotaPeriod->progress_percent,
                'is_current' => $quotaPeriod->isCurrent(),
            ]),
        ]);
    }

    /**
     * Update a period.
     */
    public function update(Request $request, QuotaPeriod $quotaPeriod): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'is_active' => 'nullable|boolean',
        ]);

        $quotaPeriod->update($validated);

        return response()->json([
            'data' => $quotaPeriod,
            'message' => 'Period updated successfully',
        ]);
    }

    /**
     * Delete a period.
     */
    public function destroy(QuotaPeriod $quotaPeriod): JsonResponse
    {
        if ($quotaPeriod->quotas()->exists()) {
            return response()->json([
                'message' => 'Cannot delete period with existing quotas. Delete quotas first.',
            ], 422);
        }

        $quotaPeriod->delete();

        return response()->json([
            'message' => 'Period deleted successfully',
        ]);
    }

    /**
     * Get current period.
     */
    public function current(Request $request): JsonResponse
    {
        $type = $request->get('type', QuotaPeriod::TYPE_QUARTER);
        $period = $this->quotaService->getOrCreateCurrentPeriod($type);

        return response()->json([
            'data' => array_merge($period->toArray(), [
                'days_remaining' => $period->days_remaining,
                'days_total' => $period->days_total,
                'days_elapsed' => $period->days_elapsed,
                'progress_percent' => $period->progress_percent,
            ]),
        ]);
    }

    /**
     * Generate periods for a year.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'types' => 'required|array|min:1',
            'types.*' => 'in:month,quarter,year',
        ]);

        $created = [];

        foreach ($validated['types'] as $type) {
            if ($type === 'year') {
                $existing = DB::table('quota_periods')->where('period_type', 'year')
                    ->whereYear('start_date', $validated['year'])
                    ->exists();

                if (!$existing) {
                    $created[] = QuotaPeriod::createYearPeriod($validated['year']);
                }
            } elseif ($type === 'quarter') {
                for ($q = 1; $q <= 4; $q++) {
                    $existing = DB::table('quota_periods')->where('period_type', 'quarter')
                        ->whereYear('start_date', $validated['year'])
                        ->whereRaw("EXTRACT(QUARTER FROM start_date) = ?", [$q])
                        ->exists();

                    if (!$existing) {
                        $created[] = QuotaPeriod::createQuarterPeriod($validated['year'], $q);
                    }
                }
            } elseif ($type === 'month') {
                for ($m = 1; $m <= 12; $m++) {
                    $existing = DB::table('quota_periods')->where('period_type', 'month')
                        ->whereYear('start_date', $validated['year'])
                        ->whereMonth('start_date', $m)
                        ->exists();

                    if (!$existing) {
                        $created[] = QuotaPeriod::createMonthPeriod($validated['year'], $m);
                    }
                }
            }
        }

        return response()->json([
            'data' => $created,
            'message' => count($created) . ' periods generated',
        ], 201);
    }
}
