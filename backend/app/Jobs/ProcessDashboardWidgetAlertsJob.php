<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Services\Reporting\DashboardAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessDashboardWidgetAlertsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public function __construct(
        public ?string $tenantId = null
    ) {}

    public function handle(DashboardAlertService $alertService): void
    {
        // Get all dashboards with active alerts
        $dashboardIds = DashboardWidgetAlert::active()
            ->join('dashboard_widgets', 'dashboard_widget_alerts.widget_id', '=', 'dashboard_widgets.id')
            ->distinct()
            ->pluck('dashboard_widgets.dashboard_id')
            ->toArray();

        if (empty($dashboardIds)) {
            return;
        }

        foreach ($dashboardIds as $dashboardId) {
            try {
                $this->processDashboardAlerts($dashboardId, $alertService);
            } catch (\Exception $e) {
                Log::error('Failed to process alerts for dashboard', [
                    'dashboard_id' => $dashboardId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    protected function processDashboardAlerts(int $dashboardId, DashboardAlertService $alertService): void
    {
        $dashboard = Dashboard::with('widgets')->find($dashboardId);

        if (! $dashboard) {
            return;
        }

        // Get widget data
        $widgetData = $this->fetchWidgetData($dashboard);

        if (empty($widgetData)) {
            return;
        }

        // Process alerts
        $triggeredAlerts = $alertService->processAlertsForDashboard($dashboardId, $widgetData);

        if (! empty($triggeredAlerts)) {
            Log::info('Dashboard alerts triggered', [
                'dashboard_id' => $dashboardId,
                'triggered_count' => count($triggeredAlerts),
            ]);
        }
    }

    protected function fetchWidgetData(Dashboard $dashboard): array
    {
        $widgetData = [];

        foreach ($dashboard->widgets as $widget) {
            try {
                // Only process KPI-type widgets that can have alerts
                if (! in_array($widget->type, ['kpi', 'goal_kpi', 'progress'])) {
                    continue;
                }

                // Fetch widget data using the widget's configured data source
                $data = $this->fetchSingleWidgetData($widget);

                if ($data !== null) {
                    $widgetData[$widget->id] = $data;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch widget data for alert processing', [
                    'widget_id' => $widget->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $widgetData;
    }

    protected function fetchSingleWidgetData($widget): mixed
    {
        // Use the existing report execution logic
        if ($widget->report_id) {
            // Execute the associated report
            $reportService = app(\App\Application\Services\Reporting\ReportService::class);

            return $reportService->execute($widget->report_id);
        }

        // For widgets with direct configuration
        $config = $widget->config;

        if (! empty($config['module_id'])) {
            // Build a query based on widget config
            return $this->executeWidgetQuery($widget);
        }

        return null;
    }

    protected function executeWidgetQuery($widget): mixed
    {
        $config = $widget->config;
        $moduleId = $config['module_id'] ?? null;

        if (! $moduleId) {
            return null;
        }

        // Get the module
        $module = DB::table('modules')->where('id', $moduleId)->first();

        if (! $module) {
            return null;
        }

        // Build aggregation query
        $aggregation = $config['aggregation'] ?? 'count';
        $field = $config['field'] ?? null;
        $filters = $config['filters'] ?? [];

        // This would use the RecordQueryBuilder service
        // For now, return a basic count
        $modelClass = $module->model_class;

        if (! class_exists($modelClass)) {
            return null;
        }

        $query = $modelClass::query();

        // Apply filters
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? 'equals';
            $value = $filter['value'] ?? null;

            if ($field && $value !== null) {
                $query->where($field, $this->convertOperator($operator), $value);
            }
        }

        // Execute aggregation
        return match ($aggregation) {
            'count' => $query->count(),
            'sum' => $query->sum($field ?? 'id'),
            'avg' => $query->avg($field ?? 'id'),
            'min' => $query->min($field ?? 'id'),
            'max' => $query->max($field ?? 'id'),
            default => $query->count(),
        };
    }

    protected function convertOperator(string $operator): string
    {
        return match ($operator) {
            'equals' => '=',
            'not_equals' => '!=',
            'greater_than' => '>',
            'less_than' => '<',
            'greater_than_or_equal' => '>=',
            'less_than_or_equal' => '<=',
            'contains' => 'LIKE',
            default => '=',
        };
    }
}
