<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the default dashboards for a new tenant.
 */
class DefaultDashboardsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating default dashboards...');

        $this->createSalesDashboard();
        $this->createSupportDashboard();
        $this->createExecutiveDashboard();
        $this->createActivityDashboard();
        $this->createFinancialDashboard();

        $this->command->info('Default dashboards created successfully!');
    }

    private function createDashboard(array $data): Dashboard
    {
        return Dashboard::firstOrCreate(
            ['name' => $data['name']],
            $data
        );
    }

    private int $currentX = 0;
    private int $currentY = 0;
    private int $maxRowHeight = 0;

    private function createWidget(Dashboard $dashboard, array $data): DashboardWidget
    {
        // Convert old size/position to grid_position
        if (isset($data['size']) || isset($data['position'])) {
            $w = $data['size']['w'] ?? 3;
            $h = $data['size']['h'] ?? 2;

            // Auto-layout: 12-column grid
            if ($this->currentX + $w > 12) {
                $this->currentX = 0;
                $this->currentY += $this->maxRowHeight;
                $this->maxRowHeight = 0;
            }

            $data['grid_position'] = [
                'x' => $this->currentX,
                'y' => $this->currentY,
                'w' => $w,
                'h' => $h,
            ];

            $this->currentX += $w;
            $this->maxRowHeight = max($this->maxRowHeight, $h);

            unset($data['size'], $data['position']);
        }

        return DashboardWidget::firstOrCreate(
            [
                'dashboard_id' => $dashboard->id,
                'title' => $data['title'],
            ],
            array_merge(['dashboard_id' => $dashboard->id], $data)
        );
    }

    private function resetGrid(): void
    {
        $this->currentX = 0;
        $this->currentY = 0;
        $this->maxRowHeight = 0;
    }

    private function createSalesDashboard(): void
    {
        $this->resetGrid();
        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $tasksModule = DB::table('modules')->where('api_name', 'tasks')->first();

        $dashboard = $this->createDashboard([
            'name' => 'Sales Dashboard',
            'description' => 'Sales pipeline, revenue metrics, and deal tracking',
            'user_id' => 1,
            'is_default' => true,
            'is_public' => true,
            'refresh_interval' => 300,
            'settings' => ['role_default' => ['sales_rep', 'manager']],
        ]);

        $position = 0;

        // KPI Row 1 - Each KPI takes 1 column in a 4-column grid
        if ($dealsModule) {
            $this->createWidget($dashboard, [
                'title' => 'Total Pipeline Value',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 3, 'h' => 2, 'minW' => 2, 'minH' => 2],
                'config' => [
                    'module_id' => $dealsModule->id,
                    'aggregation' => 'sum',
                    'field' => 'amount',
                    'filters' => [
                        ['field' => 'stage', 'operator' => 'not_in', 'value' => ['closed_won', 'closed_lost']]
                    ],
                    'format' => 'currency',
                    'icon' => 'trending-up',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Deals to Close This Month',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 3, 'h' => 2, 'minW' => 2, 'minH' => 2],
                'config' => [
                    'module_id' => $dealsModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'stage', 'operator' => 'not_in', 'value' => ['closed_won', 'closed_lost']]
                    ],
                    'date_range' => ['field' => 'close_date', 'range' => 'this_month'],
                    'icon' => 'calendar',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Won This Month',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 3, 'h' => 2, 'minW' => 2, 'minH' => 2],
                'config' => [
                    'module_id' => $dealsModule->id,
                    'aggregation' => 'sum',
                    'field' => 'amount',
                    'filters' => [
                        ['field' => 'stage', 'operator' => 'equals', 'value' => 'closed_won']
                    ],
                    'date_range' => ['field' => 'close_date', 'range' => 'this_month'],
                    'compare_range' => 'last_month',
                    'format' => 'currency',
                    'icon' => 'dollar-sign',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Deals Won',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 3, 'h' => 2, 'minW' => 2, 'minH' => 2],
                'config' => [
                    'module_id' => $dealsModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'stage', 'operator' => 'equals', 'value' => 'closed_won']
                    ],
                    'date_range' => ['field' => 'close_date', 'range' => 'this_month'],
                    'compare_range' => 'last_month',
                    'icon' => 'check-circle',
                ],
            ]);
        }

        // Charts Row 2
        $pipelineReport = DB::table('reports')->where('name', 'Sales Pipeline')->first();
        if ($pipelineReport) {
            $this->createWidget($dashboard, [
                'report_id' => $pipelineReport->id,
                'title' => 'Sales Pipeline',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        $revenueReport = DB::table('reports')->where('name', 'Monthly Revenue')->first();
        if ($revenueReport) {
            $this->createWidget($dashboard, [
                'report_id' => $revenueReport->id,
                'title' => 'Monthly Revenue',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        // Row 3: Top Deals and Tasks
        $topDealsReport = DB::table('reports')->where('name', 'Top Deals')->first();
        if ($topDealsReport) {
            $this->createWidget($dashboard, [
                'report_id' => $topDealsReport->id,
                'title' => 'Top Deals',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 1],
            ]);
        }

        if ($tasksModule) {
            $this->createWidget($dashboard, [
                'title' => 'My Tasks',
                'type' => 'tasks',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 1],
                'config' => [
                    'filter' => 'assigned_to_me',
                    'limit' => 5,
                ],
            ]);
        }

        // Row 4: Activity Feed
        $this->createWidget($dashboard, [
            'title' => 'Recent Activity',
            'type' => 'activity',
            'position' => $position++,
            'size' => ['w' => 4, 'h' => 1],
            'config' => [
                'date_range' => 'last_7_days',
                'limit' => 10,
            ],
        ]);

        $this->command->info('  - Created Sales Dashboard with ' . $position . ' widgets');
    }

    private function createSupportDashboard(): void
    {
        $this->resetGrid();
        $casesModule = DB::table('modules')->where('api_name', 'cases')->first();

        $dashboard = $this->createDashboard([
            'name' => 'Support Dashboard',
            'description' => 'Case tracking, SLA monitoring, and support metrics',
            'user_id' => 1,
            'is_default' => false,
            'is_public' => true,
            'refresh_interval' => 300,
            'settings' => ['role_default' => ['support']],
        ]);

        $position = 0;

        // KPI Row 1
        if ($casesModule) {
            $this->createWidget($dashboard, [
                'title' => 'Open Cases',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $casesModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'status', 'operator' => 'not_in', 'value' => ['resolved', 'closed']]
                    ],
                    'icon' => 'inbox',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Critical Cases',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $casesModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'priority', 'operator' => 'equals', 'value' => 'critical'],
                        ['field' => 'status', 'operator' => 'not_in', 'value' => ['resolved', 'closed']]
                    ],
                    'icon' => 'alert-triangle',
                    'color' => 'red',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Resolved Today',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $casesModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'status', 'operator' => 'in', 'value' => ['resolved', 'closed']]
                    ],
                    'date_range' => ['field' => 'resolution_date', 'range' => 'today'],
                    'icon' => 'check-circle',
                    'color' => 'green',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Escalated',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $casesModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'escalated', 'operator' => 'equals', 'value' => true],
                        ['field' => 'status', 'operator' => 'not_in', 'value' => ['resolved', 'closed']]
                    ],
                    'icon' => 'arrow-up-circle',
                    'color' => 'orange',
                ],
            ]);
        }

        // Charts Row 2
        $priorityReport = DB::table('reports')->where('name', 'Open Cases by Priority')->first();
        if ($priorityReport) {
            $this->createWidget($dashboard, [
                'report_id' => $priorityReport->id,
                'title' => 'Cases by Priority',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        $statusReport = DB::table('reports')->where('name', 'Cases by Status')->first();
        if ($statusReport) {
            $this->createWidget($dashboard, [
                'report_id' => $statusReport->id,
                'title' => 'Cases by Status',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        // Row 3: Tables
        $overdueReport = DB::table('reports')->where('name', 'Overdue Cases')->first();
        if ($overdueReport) {
            $this->createWidget($dashboard, [
                'report_id' => $overdueReport->id,
                'title' => 'Overdue Cases',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 1],
            ]);
        }

        if ($casesModule) {
            $this->createWidget($dashboard, [
                'title' => 'My Cases',
                'type' => 'table',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 1],
                'config' => [
                    'module_id' => $casesModule->id,
                    'filter' => 'assigned_to_me',
                    'filters' => [
                        ['field' => 'status', 'operator' => 'not_in', 'value' => ['resolved', 'closed']]
                    ],
                    'columns' => ['case_number', 'subject', 'priority', 'sla_due_date'],
                    'limit' => 10,
                ],
            ]);
        }

        // Row 4: Trend
        $trendReport = DB::table('reports')->where('name', 'Case Volume Trend')->first();
        if ($trendReport) {
            $this->createWidget($dashboard, [
                'report_id' => $trendReport->id,
                'title' => 'Case Trend',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 4, 'h' => 1],
            ]);
        }

        $this->command->info('  - Created Support Dashboard with ' . $position . ' widgets');
    }

    private function createExecutiveDashboard(): void
    {
        $this->resetGrid();
        $dealsModule = DB::table('modules')->where('api_name', 'deals')->first();
        $orgsModule = DB::table('modules')->where('api_name', 'organizations')->first();

        $dashboard = $this->createDashboard([
            'name' => 'Executive Dashboard',
            'description' => 'High-level business metrics and performance overview',
            'user_id' => 1,
            'is_default' => false,
            'is_public' => true,
            'refresh_interval' => 600,
            'settings' => ['role_default' => ['admin', 'manager']],
        ]);

        $position = 0;

        // KPI Row 1
        if ($dealsModule) {
            $this->createWidget($dashboard, [
                'title' => 'Revenue YTD',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $dealsModule->id,
                    'aggregation' => 'sum',
                    'field' => 'amount',
                    'filters' => [
                        ['field' => 'stage', 'operator' => 'equals', 'value' => 'closed_won']
                    ],
                    'date_range' => ['field' => 'close_date', 'range' => 'this_year'],
                    'format' => 'currency',
                    'icon' => 'dollar-sign',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Active Deals',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $dealsModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'stage', 'operator' => 'not_in', 'value' => ['closed_won', 'closed_lost']]
                    ],
                    'icon' => 'briefcase',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Pipeline Value',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $dealsModule->id,
                    'aggregation' => 'sum',
                    'field' => 'amount',
                    'filters' => [
                        ['field' => 'stage', 'operator' => 'not_in', 'value' => ['closed_won', 'closed_lost']]
                    ],
                    'format' => 'currency',
                    'icon' => 'trending-up',
                ],
            ]);
        }

        if ($orgsModule) {
            $this->createWidget($dashboard, [
                'title' => 'New Customers',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $orgsModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'type', 'operator' => 'equals', 'value' => 'customer']
                    ],
                    'date_range' => ['field' => 'created_at', 'range' => 'this_month'],
                    'compare_range' => 'last_month',
                    'icon' => 'users',
                ],
            ]);
        }

        // Charts Row 2
        $revenueReport = DB::table('reports')->where('name', 'Monthly Revenue')->first();
        if ($revenueReport) {
            $this->createWidget($dashboard, [
                'report_id' => $revenueReport->id,
                'title' => 'Revenue Trend',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        $repReport = DB::table('reports')->where('name', 'Sales by Rep')->first();
        if ($repReport) {
            $this->createWidget($dashboard, [
                'report_id' => $repReport->id,
                'title' => 'Sales by Rep',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        // Row 3
        $sourceReport = DB::table('reports')->where('name', 'Deal Source Analysis')->first();
        if ($sourceReport) {
            $this->createWidget($dashboard, [
                'report_id' => $sourceReport->id,
                'title' => 'Revenue by Source',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        $winLossReport = DB::table('reports')->where('name', 'Win/Loss Analysis')->first();
        if ($winLossReport) {
            $this->createWidget($dashboard, [
                'report_id' => $winLossReport->id,
                'title' => 'Win/Loss Analysis',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        $this->command->info('  - Created Executive Dashboard with ' . $position . ' widgets');
    }

    private function createActivityDashboard(): void
    {
        $this->resetGrid();
        $activitiesModule = DB::table('modules')->where('api_name', 'activities')->first();
        $tasksModule = DB::table('modules')->where('api_name', 'tasks')->first();
        $eventsModule = DB::table('modules')->where('api_name', 'events')->first();

        $dashboard = $this->createDashboard([
            'name' => 'Activity Dashboard',
            'description' => 'Daily activities, tasks, and calendar overview',
            'user_id' => 1,
            'is_default' => false,
            'is_public' => true,
            'refresh_interval' => 300,
        ]);

        $position = 0;

        // KPI Row 1
        if ($activitiesModule) {
            $this->createWidget($dashboard, [
                'title' => 'Calls Today',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $activitiesModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'type', 'operator' => 'equals', 'value' => 'call']
                    ],
                    'date_range' => ['field' => 'start_datetime', 'range' => 'today'],
                    'icon' => 'phone',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Meetings Today',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $activitiesModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'type', 'operator' => 'equals', 'value' => 'meeting']
                    ],
                    'date_range' => ['field' => 'start_datetime', 'range' => 'today'],
                    'icon' => 'users',
                ],
            ]);
        }

        if ($tasksModule) {
            $this->createWidget($dashboard, [
                'title' => 'Tasks Completed',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $tasksModule->id,
                    'aggregation' => 'count',
                    'filters' => [
                        ['field' => 'status', 'operator' => 'equals', 'value' => 'completed']
                    ],
                    'date_range' => ['field' => 'updated_at', 'range' => 'today'],
                    'icon' => 'check-circle',
                    'color' => 'green',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Pending Tasks',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $tasksModule->id,
                    'aggregation' => 'count',
                    'filter' => 'assigned_to_me',
                    'filters' => [
                        ['field' => 'status', 'operator' => 'not_equals', 'value' => 'completed']
                    ],
                    'icon' => 'clock',
                ],
            ]);
        }

        // Charts Row 2
        $summaryReport = DB::table('reports')->where('name', 'Activity Summary')->first();
        if ($summaryReport) {
            $this->createWidget($dashboard, [
                'report_id' => $summaryReport->id,
                'title' => 'Activity by Type',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        $callsReport = DB::table('reports')->where('name', 'Call Outcomes')->first();
        if ($callsReport) {
            $this->createWidget($dashboard, [
                'report_id' => $callsReport->id,
                'title' => 'Call Outcomes',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        // Row 3
        if ($eventsModule) {
            $this->createWidget($dashboard, [
                'title' => 'My Calendar',
                'type' => 'calendar',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
                'config' => [
                    'module_id' => $eventsModule->id,
                    'filter' => 'my_events',
                ],
            ]);
        }

        if ($tasksModule) {
            $this->createWidget($dashboard, [
                'title' => 'My Tasks',
                'type' => 'tasks',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
                'config' => [
                    'filter' => 'assigned_to_me',
                    'filters' => [
                        ['field' => 'status', 'operator' => 'not_equals', 'value' => 'completed']
                    ],
                    'limit' => 10,
                ],
            ]);
        }

        $this->command->info('  - Created Activity Dashboard with ' . $position . ' widgets');
    }

    private function createFinancialDashboard(): void
    {
        $this->resetGrid();
        $invoicesModule = DB::table('modules')->where('api_name', 'invoices')->first();

        $dashboard = $this->createDashboard([
            'name' => 'Financial Dashboard',
            'description' => 'Accounts receivable, collections, and invoice tracking',
            'user_id' => 1,
            'is_default' => false,
            'is_public' => true,
            'refresh_interval' => 600,
        ]);

        $position = 0;

        // KPI Row 1
        if ($invoicesModule) {
            $this->createWidget($dashboard, [
                'title' => 'Outstanding AR',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $invoicesModule->id,
                    'aggregation' => 'sum',
                    'field' => 'balance_due',
                    'filters' => [
                        ['field' => 'status', 'operator' => 'in', 'value' => ['sent', 'overdue']]
                    ],
                    'format' => 'currency',
                    'icon' => 'credit-card',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Collected This Month',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $invoicesModule->id,
                    'aggregation' => 'sum',
                    'field' => 'amount_paid',
                    'date_range' => ['field' => 'payment_date', 'range' => 'this_month'],
                    'compare_range' => 'last_month',
                    'format' => 'currency',
                    'icon' => 'dollar-sign',
                    'color' => 'green',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Overdue Amount',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $invoicesModule->id,
                    'aggregation' => 'sum',
                    'field' => 'balance_due',
                    'filters' => [
                        ['field' => 'status', 'operator' => 'equals', 'value' => 'overdue']
                    ],
                    'format' => 'currency',
                    'icon' => 'alert-circle',
                    'color' => 'red',
                ],
            ]);

            $this->createWidget($dashboard, [
                'title' => 'Invoices Sent',
                'type' => 'kpi',
                'position' => $position++,
                'size' => ['w' => 1, 'h' => 1],
                'config' => [
                    'module_id' => $invoicesModule->id,
                    'aggregation' => 'count',
                    'date_range' => ['field' => 'invoice_date', 'range' => 'this_month'],
                    'icon' => 'file-text',
                ],
            ]);
        }

        // Charts Row 2
        $agingReport = DB::table('reports')->where('name', 'Invoice Aging')->first();
        if ($agingReport) {
            $this->createWidget($dashboard, [
                'report_id' => $agingReport->id,
                'title' => 'Invoice Aging',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        $revenueReport = DB::table('reports')->where('name', 'Collected Revenue')->first();
        if ($revenueReport) {
            $this->createWidget($dashboard, [
                'report_id' => $revenueReport->id,
                'title' => 'Monthly Collections',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 2],
            ]);
        }

        // Row 3: Tables
        $unpaidReport = DB::table('reports')->where('name', 'Unpaid Invoices')->first();
        if ($unpaidReport) {
            $this->createWidget($dashboard, [
                'report_id' => $unpaidReport->id,
                'title' => 'Unpaid Invoices',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 1],
            ]);
        }

        if ($invoicesModule) {
            $this->createWidget($dashboard, [
                'title' => 'Recent Payments',
                'type' => 'table',
                'position' => $position++,
                'size' => ['w' => 2, 'h' => 1],
                'config' => [
                    'module_id' => $invoicesModule->id,
                    'filters' => [
                        ['field' => 'status', 'operator' => 'equals', 'value' => 'paid']
                    ],
                    'sorting' => [
                        ['field' => 'payment_date', 'direction' => 'desc']
                    ],
                    'columns' => ['invoice_number', 'organization_id', 'amount_paid', 'payment_date'],
                    'limit' => 10,
                ],
            ]);
        }

        // Row 4: Status breakdown
        $statusReport = DB::table('reports')->where('name', 'Invoices by Status')->first();
        if ($statusReport) {
            $this->createWidget($dashboard, [
                'report_id' => $statusReport->id,
                'title' => 'Invoices by Status',
                'type' => 'report',
                'position' => $position++,
                'size' => ['w' => 4, 'h' => 1],
            ]);
        }

        $this->command->info('  - Created Financial Dashboard with ' . $position . ' widgets');
    }
}
