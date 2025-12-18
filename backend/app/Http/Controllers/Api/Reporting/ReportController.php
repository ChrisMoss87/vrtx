<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reporting;

use App\Application\Services\Reporting\ReportingApplicationService;
use App\Domain\Reporting\DTOs\CreateReportDTO;
use App\Domain\Reporting\ValueObjects\ChartType;
use App\Domain\Reporting\ValueObjects\DateRange;
use App\Domain\Reporting\ValueObjects\ReportType;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Reporting\ReportService;
use App\Services\Reporting\PdfExportService;
use App\Services\Reporting\ExcelExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(
        protected ReportingApplicationService $reportingService,
        protected ReportService $reportService,
        protected PdfExportService $pdfExportService,
        protected ExcelExportService $excelExportService
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

        $dto = new CreateReportDTO(
            name: $validated['name'],
            moduleId: $validated['module_id'] ?? null,
            type: ReportType::from($validated['type']),
            userId: Auth::id(),
            description: $validated['description'] ?? null,
            chartType: isset($validated['chart_type']) ? ChartType::from($validated['chart_type']) : null,
            filters: $validated['filters'] ?? [],
            grouping: $validated['grouping'] ?? [],
            aggregations: $validated['aggregations'] ?? [],
            sorting: $validated['sorting'] ?? [],
            dateRange: isset($validated['date_range']) ? DateRange::fromArray($validated['date_range']) : null,
        );

        $reportDTO = $this->reportingService->createReport($dto);

        return response()->json([
            'message' => 'Report created successfully',
            'data' => $reportDTO->toArray(),
        ], 201);
    }

    /**
     * Get a single report.
     */
    public function show(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $reportDTO = $this->reportingService->getReport($report->id);

        return response()->json([
            'data' => $reportDTO->toArray(),
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

        $reportDTO = $this->reportingService->updateReport(
            reportId: $report->id,
            name: $validated['name'] ?? $report->name,
            description: $validated['description'] ?? $report->description,
            type: isset($validated['type']) ? ReportType::from($validated['type']) : ReportType::from($report->type),
            chartType: isset($validated['chart_type']) ? ChartType::from($validated['chart_type']) : ($report->chart_type ? ChartType::from($report->chart_type) : null),
            filters: $validated['filters'] ?? $report->filters ?? [],
            grouping: $validated['grouping'] ?? $report->grouping ?? [],
            aggregations: $validated['aggregations'] ?? $report->aggregations ?? [],
            sorting: $validated['sorting'] ?? $report->sorting ?? [],
            dateRange: isset($validated['date_range']) ? DateRange::fromArray($validated['date_range']) : ($report->date_range ? DateRange::fromArray($report->date_range) : null),
            config: $validated['config'] ?? $report->config ?? [],
        );

        return response()->json([
            'message' => 'Report updated successfully',
            'data' => $reportDTO->toArray(),
        ]);
    }

    /**
     * Delete a report.
     */
    public function destroy(Report $report): JsonResponse
    {
        $this->authorize('delete', $report);

        $this->reportingService->deleteReport($report->id);

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

        $result = $this->reportingService->executeReport($report->id, $useCache);

        return response()->json([
            'data' => $result,
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
        $filename = str_replace(' ', '_', $report->name) . '_' . now()->format('Y-m-d');

        // For PDF and Excel, we need the full report data
        if (in_array($format, ['pdf', 'xlsx', 'excel'])) {
            $reportData = $this->reportService->executeReport($report);

            if ($format === 'pdf') {
                $content = $this->pdfExportService->exportReport($report, $reportData);
                return response($content)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', "attachment; filename=\"{$filename}.pdf\"");
            }

            // Excel export
            $content = $this->excelExportService->exportReport($report, $reportData);
            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}.xlsx\"");
        }

        // CSV and JSON exports (existing behavior)
        $content = $this->reportService->exportReport($report, $format);

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
