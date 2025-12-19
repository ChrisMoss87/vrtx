<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reporting;

use App\Application\Services\Reporting\ReportingApplicationService;
use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Services\Reporting\PdfExportService;
use App\Services\Reporting\ExcelExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ReportingApplicationService $reportingService,
        protected PdfExportService $pdfExportService,
        protected ExcelExportService $excelExportService
    ) {}

    /**
     * List dashboards accessible by the user.
     */
    public function index(Request $request): JsonResponse
    {
        $dashboards = Dashboard::accessibleBy(Auth::id())
            ->with('user:id,name')
            ->withCount('widgets')
            ->orderBy('is_default', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json(['data' => $dashboards]);
    }

    /**
     * Get widget types.
     */
    public function widgetTypes(): JsonResponse
    {
        return response()->json([
            'data' => DashboardWidget::getTypes(),
        ]);
    }

    /**
     * Create a new dashboard.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'is_default' => 'boolean',
            'layout' => 'array',
            'settings' => 'array',
            'filters' => 'array',
            'refresh_interval' => 'integer|min:0',
        ]);

        $dashboard = Dashboard::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        if ($dashboard->is_default) {
            $dashboard->setAsDefault();
        }

        return response()->json([
            'message' => 'Dashboard created successfully',
            'data' => $dashboard,
        ], 201);
    }

    /**
     * Get a single dashboard with widgets.
     */
    public function show(Dashboard $dashboard): JsonResponse
    {
        $this->authorize('view', $dashboard);

        $dashboard->load([
            'widgets.report:id,name,type,chart_type',
            'user:id,name',
        ]);

        return response()->json(['data' => $dashboard]);
    }

    /**
     * Update a dashboard.
     */
    public function update(Request $request, Dashboard $dashboard): JsonResponse
    {
        $this->authorize('update', $dashboard);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'is_default' => 'boolean',
            'layout' => 'array',
            'settings' => 'array',
            'filters' => 'array',
            'refresh_interval' => 'integer|min:0',
        ]);

        $dashboard->update($validated);

        if (isset($validated['is_default']) && $validated['is_default']) {
            $dashboard->setAsDefault();
        }

        return response()->json([
            'message' => 'Dashboard updated successfully',
            'data' => $dashboard->fresh(),
        ]);
    }

    /**
     * Delete a dashboard.
     */
    public function destroy(Dashboard $dashboard): JsonResponse
    {
        $this->authorize('delete', $dashboard);

        $dashboard->delete();

        return response()->json([
            'message' => 'Dashboard deleted successfully',
        ]);
    }

    /**
     * Duplicate a dashboard.
     */
    public function duplicate(Dashboard $dashboard): JsonResponse
    {
        $this->authorize('view', $dashboard);

        $newDashboard = $dashboard->duplicate(Auth::id());

        return response()->json([
            'message' => 'Dashboard duplicated successfully',
            'data' => $newDashboard->load('widgets'),
        ], 201);
    }

    /**
     * Set dashboard as default.
     */
    public function setDefault(Dashboard $dashboard): JsonResponse
    {
        $this->authorize('update', $dashboard);

        $dashboard->setAsDefault();

        return response()->json([
            'message' => 'Dashboard set as default',
            'data' => $dashboard,
        ]);
    }

    /**
     * Update dashboard layout.
     */
    public function updateLayout(Request $request, Dashboard $dashboard): JsonResponse
    {
        $this->authorize('update', $dashboard);

        $validated = $request->validate([
            'layout' => 'required|array',
        ]);

        $dashboard->update(['layout' => $validated['layout']]);

        return response()->json([
            'message' => 'Layout updated successfully',
            'data' => $dashboard->fresh(),
        ]);
    }

    /**
     * Add a widget to the dashboard.
     */
    public function addWidget(Request $request, Dashboard $dashboard): JsonResponse
    {
        $this->authorize('update', $dashboard);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:report,kpi,chart,table,activity,pipeline,tasks,calendar,text,iframe,goal_kpi,leaderboard,funnel,progress,recent_records',
            'report_id' => 'nullable|integer|exists:reports,id',
            'config' => 'array',
            'grid_position' => 'array',
            'grid_position.x' => 'integer|min:0',
            'grid_position.y' => 'integer|min:0',
            'grid_position.w' => 'integer|min:1|max:12',
            'grid_position.h' => 'integer|min:1',
        ]);

        // Calculate auto-position if not provided (find next available spot)
        if (!isset($validated['grid_position'])) {
            $maxY = $dashboard->widgets()->max(\DB::raw("(grid_position->>'y')::int")) ?? -1;
            $validated['grid_position'] = [
                'x' => 0,
                'y' => max(0, $maxY + 4),
                'w' => 6,
                'h' => 4,
            ];
        }

        $widget = $dashboard->widgets()->create($validated);

        return response()->json([
            'message' => 'Widget added successfully',
            'data' => $widget->load('report:id,name,type,chart_type'),
        ], 201);
    }

    /**
     * Update a widget.
     */
    public function updateWidget(Request $request, Dashboard $dashboard, DashboardWidget $widget): JsonResponse
    {
        $this->authorize('update', $dashboard);

        // Ensure widget belongs to dashboard
        if ($widget->dashboard_id !== $dashboard->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|in:report,kpi,chart,table,activity,pipeline,tasks,calendar,text,iframe,goal_kpi,leaderboard,funnel,progress,recent_records',
            'report_id' => 'nullable|integer|exists:reports,id',
            'config' => 'array',
            'grid_position' => 'array',
            'grid_position.x' => 'integer|min:0',
            'grid_position.y' => 'integer|min:0',
            'grid_position.w' => 'integer|min:1|max:12',
            'grid_position.h' => 'integer|min:1',
            'refresh_interval' => 'integer|min:0',
        ]);

        $widget->update($validated);

        return response()->json([
            'message' => 'Widget updated successfully',
            'data' => $widget->fresh()->load('report:id,name,type,chart_type'),
        ]);
    }

    /**
     * Remove a widget from the dashboard.
     */
    public function removeWidget(Dashboard $dashboard, DashboardWidget $widget): JsonResponse
    {
        $this->authorize('update', $dashboard);

        // Ensure widget belongs to dashboard
        if ($widget->dashboard_id !== $dashboard->id) {
            abort(404);
        }

        $widget->delete();

        return response()->json([
            'message' => 'Widget removed successfully',
        ]);
    }

    /**
     * Update widget grid positions (batch update for drag/drop/resize).
     */
    public function updateWidgetPositions(Request $request, Dashboard $dashboard): JsonResponse
    {
        $this->authorize('update', $dashboard);

        $validated = $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|integer|exists:dashboard_widgets,id',
            'widgets.*.x' => 'required|integer|min:0',
            'widgets.*.y' => 'required|integer|min:0',
            'widgets.*.w' => 'required|integer|min:1|max:12',
            'widgets.*.h' => 'required|integer|min:1',
        ]);

        foreach ($validated['widgets'] as $item) {
            DashboardWidget::where('id', $item['id'])
                ->where('dashboard_id', $dashboard->id)
                ->update([
                    'grid_position' => [
                        'x' => $item['x'],
                        'y' => $item['y'],
                        'w' => $item['w'],
                        'h' => $item['h'],
                    ],
                ]);
        }

        return response()->json([
            'message' => 'Widget positions updated successfully',
        ]);
    }

    /**
     * Get widget data.
     */
    public function widgetData(Dashboard $dashboard, DashboardWidget $widget): JsonResponse
    {
        $this->authorize('view', $dashboard);

        // Ensure widget belongs to dashboard
        if ($widget->dashboard_id !== $dashboard->id) {
            abort(404);
        }

        // If widget has a report, execute it
        if ($widget->report_id && $widget->report) {
            $data = $this->reportingService->executeReport($widget->report_id);
        } else {
            $data = $widget->getData();
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Get data for all widgets on a dashboard.
     */
    public function allWidgetData(Dashboard $dashboard): JsonResponse
    {
        $this->authorize('view', $dashboard);

        $dashboard->load('widgets.report');

        $widgetData = [];

        foreach ($dashboard->widgets as $widget) {
            if ($widget->report_id && $widget->report) {
                $widgetData[$widget->id] = $this->reportingService->executeReport($widget->report_id);
            } else {
                $widgetData[$widget->id] = $widget->getData();
            }
        }

        return response()->json(['data' => $widgetData]);
    }

    /**
     * Export dashboard to PDF or Excel.
     */
    public function export(Dashboard $dashboard, Request $request): Response
    {
        $this->authorize('view', $dashboard);

        $format = $request->input('format', 'pdf');
        $filename = str_replace(' ', '_', $dashboard->name) . '_' . now()->format('Y-m-d');

        // Gather all widget data
        $dashboard->load('widgets.report');
        $widgetData = [];

        foreach ($dashboard->widgets as $widget) {
            $data = null;
            if ($widget->report_id && $widget->report) {
                $data = $this->reportingService->executeReport($widget->report_id);
            } else {
                $data = $widget->getData();
            }

            $widgetData[] = [
                'id' => $widget->id,
                'title' => $widget->title,
                'type' => $widget->type,
                'chart_type' => $widget->config['chart_type'] ?? null,
                'data' => $data,
            ];
        }

        if ($format === 'pdf') {
            $content = $this->pdfExportService->exportDashboard($dashboard, $widgetData);
            return response($content)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}.pdf\"");
        }

        // Excel export
        $content = $this->excelExportService->exportDashboard($dashboard, $widgetData);
        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}.xlsx\"");
    }
}
