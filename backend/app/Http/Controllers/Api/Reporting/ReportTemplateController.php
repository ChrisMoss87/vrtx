<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reporting;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportTemplateController extends Controller
{
    /**
     * List templates accessible by the user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReportTemplate::accessibleBy(Auth::id())
            ->with('module:id,name,api_name')
            ->with('user:id,name');

        // Filter by module
        if ($request->has('module_id')) {
            $query->forModule($request->input('module_id'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'ilike', '%' . $request->input('search') . '%');
        }

        $templates = $query->orderBy('name')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    /**
     * Create a new template.
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
            'date_range' => 'nullable|array',
        ]);

        $template = DB::table('report_templates')->insertGetId([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Template created successfully',
            'data' => $template->load(['module:id,name,api_name', 'user:id,name']),
        ], 201);
    }

    /**
     * Get a single template.
     */
    public function show(ReportTemplate $reportTemplate): JsonResponse
    {
        // Check access - must be owner or public
        if ($reportTemplate->user_id !== Auth::id() && !$reportTemplate->is_public) {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'data' => $reportTemplate->load(['module:id,name,api_name', 'user:id,name']),
        ]);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, ReportTemplate $reportTemplate): JsonResponse
    {
        // Only owner can update
        if ($reportTemplate->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

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
            'date_range' => 'nullable|array',
        ]);

        $reportTemplate->update($validated);

        return response()->json([
            'message' => 'Template updated successfully',
            'data' => $reportTemplate->load(['module:id,name,api_name', 'user:id,name']),
        ]);
    }

    /**
     * Delete a template.
     */
    public function destroy(ReportTemplate $reportTemplate): JsonResponse
    {
        // Only owner can delete
        if ($reportTemplate->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $reportTemplate->delete();

        return response()->json([
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Create a report from a template.
     */
    public function apply(Request $request, ReportTemplate $reportTemplate): JsonResponse
    {
        // Check access - must be owner or public
        if ($reportTemplate->user_id !== Auth::id() && !$reportTemplate->is_public) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $reportData = $reportTemplate->toReportData();

        $report = DB::table('reports')->insertGetId([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $reportTemplate->description,
            'user_id' => Auth::id(),
            'is_public' => $validated['is_public'] ?? false,
            'is_favorite' => false,
            ...$reportData,
        ]);

        return response()->json([
            'message' => 'Report created from template',
            'data' => $report->load(['module:id,name,api_name', 'user:id,name']),
        ], 201);
    }

    /**
     * Create a template from an existing report.
     */
    public function createFromReport(Request $request, Report $report): JsonResponse
    {
        // Check if user can view the report
        if ($report->user_id !== Auth::id() && !$report->is_public) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $template = DB::table('report_templates')->insertGetId([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $report->description,
            'user_id' => Auth::id(),
            'module_id' => $report->module_id,
            'type' => $report->type,
            'chart_type' => $report->chart_type,
            'is_public' => $validated['is_public'] ?? false,
            'config' => $report->config ?? [],
            'filters' => $report->filters ?? [],
            'grouping' => $report->grouping ?? [],
            'aggregations' => $report->aggregations ?? [],
            'sorting' => $report->sorting ?? [],
            'date_range' => $report->date_range,
        ]);

        return response()->json([
            'message' => 'Template created from report',
            'data' => $template->load(['module:id,name,api_name', 'user:id,name']),
        ], 201);
    }
}
