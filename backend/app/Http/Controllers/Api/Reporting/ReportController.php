<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reporting;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Reporting\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * List reports accessible by the user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Report::accessibleBy(Auth::id())
            ->with('module:id,name,api_name')
            ->with('user:id,name');

        // Filter by module
        if ($request->has('module_id')) {
            $query->forModule($request->input('module_id'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->ofType($request->input('type'));
        }

        // Filter favorites only
        if ($request->boolean('favorites')) {
            $query->favorites();
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'ilike', '%' . $request->input('search') . '%');
        }

        $reports = $query->orderBy('is_favorite', 'desc')
            ->orderBy('updated_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $reports->items(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * Get report types and chart types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'report_types' => Report::getTypes(),
            'chart_types' => Report::getChartTypes(),
            'aggregations' => Report::getAggregations(),
        ]);
    }

    /**
     * Get fields available for a module.
     */
    public function fields(Request $request): JsonResponse
    {
        $request->validate([
            'module_id' => 'required|integer|exists:modules,id',
        ]);

        $fields = $this->reportService->getModuleFields($request->input('module_id'));

        return response()->json(['data' => $fields]);
    }

    /**
     * Create a new report.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module_id' => 'nullable|integer|exists:modules,id',
            'type' => 'required|string|in:table,chart,summary,matrix,pivot',
            'chart_type' => 'nullable|string|in:bar,line,pie,doughnut,area,funnel,scatter,gauge,kpi',
            'is_public' => 'boolean',
            'config' => 'array',
            'filters' => 'array',
            'grouping' => 'array',
            'aggregations' => 'array',
            'sorting' => 'array',
            'date_range' => 'array',
        ]);

        $report = Report::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Report created successfully',
            'data' => $report->load('module:id,name,api_name'),
        ], 201);
    }

    /**
     * Get a single report.
     */
    public function show(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        return response()->json([
            'data' => $report->load('module:id,name,api_name', 'user:id,name'),
        ]);
    }

    /**
     * Update a report.
     */
    public function update(Request $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'module_id' => 'nullable|integer|exists:modules,id',
            'type' => 'sometimes|required|string|in:table,chart,summary,matrix,pivot',
            'chart_type' => 'nullable|string|in:bar,line,pie,doughnut,area,funnel,scatter,gauge,kpi',
            'is_public' => 'boolean',
            'config' => 'array',
            'filters' => 'array',
            'grouping' => 'array',
            'aggregations' => 'array',
            'sorting' => 'array',
            'date_range' => 'array',
        ]);

        $report->update($validated);

        // Clear cache when report is updated
        $report->clearCache();

        return response()->json([
            'message' => 'Report updated successfully',
            'data' => $report->fresh()->load('module:id,name,api_name'),
        ]);
    }

    /**
     * Delete a report.
     */
    public function destroy(Report $report): JsonResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return response()->json([
            'message' => 'Report deleted successfully',
        ]);
    }

    /**
     * Execute a report and get results.
     */
    public function execute(Report $report, Request $request): JsonResponse
    {
        $this->authorize('view', $report);

        $useCache = !$request->boolean('refresh');

        $result = $this->reportService->executeReport($report, $useCache);

        return response()->json([
            'data' => $result,
            'cached' => $report->isCacheValid(),
            'last_run_at' => $report->last_run_at?->toIso8601String(),
        ]);
    }

    /**
     * Preview a report without saving.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => 'nullable|integer|exists:modules,id',
            'type' => 'required|string|in:table,chart,summary,matrix,pivot',
            'chart_type' => 'nullable|string',
            'config' => 'array',
            'filters' => 'array',
            'grouping' => 'array',
            'aggregations' => 'array',
            'sorting' => 'array',
            'date_range' => 'array',
        ]);

        $result = $this->reportService->executeReportQuery($validated);

        return response()->json(['data' => $result]);
    }

    /**
     * Toggle favorite status.
     */
    public function toggleFavorite(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $report->update(['is_favorite' => !$report->is_favorite]);

        return response()->json([
            'message' => $report->is_favorite ? 'Added to favorites' : 'Removed from favorites',
            'is_favorite' => $report->is_favorite,
        ]);
    }

    /**
     * Duplicate a report.
     */
    public function duplicate(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $newReport = $report->replicate();
        $newReport->name = $report->name . ' (Copy)';
        $newReport->user_id = Auth::id();
        $newReport->is_public = false;
        $newReport->is_favorite = false;
        $newReport->cached_result = null;
        $newReport->cache_expires_at = null;
        $newReport->last_run_at = null;
        $newReport->save();

        return response()->json([
            'message' => 'Report duplicated successfully',
            'data' => $newReport->load('module:id,name,api_name'),
        ], 201);
    }

    /**
     * Export report data.
     */
    public function export(Report $report, Request $request): Response
    {
        $this->authorize('view', $report);

        $format = $request->input('format', 'csv');

        $content = $this->reportService->exportReport($report, $format);

        $filename = str_replace(' ', '_', $report->name) . '_' . now()->format('Y-m-d');

        return match ($format) {
            'csv' => response($content)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}.csv\""),
            'json' => response($content)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}.json\""),
            default => response($content)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}.csv\""),
        };
    }

    /**
     * Get KPI value.
     */
    public function kpi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => 'nullable|integer|exists:modules,id',
            'aggregation' => 'required|string|in:count,sum,avg,min,max',
            'field' => 'nullable|string',
            'filters' => 'array',
            'date_range' => 'array',
            'compare_range' => 'nullable|array',
        ]);

        $result = $this->reportService->calculateKpi($validated);

        return response()->json(['data' => $result]);
    }
}
