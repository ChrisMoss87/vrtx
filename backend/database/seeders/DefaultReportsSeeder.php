<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Report;
use Illuminate\Database\Seeder;

/**
 * Seeds the default reports for a new tenant.
 */
class DefaultReportsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating default reports...');

        $this->createSalesReports();
        $this->createCustomerReports();
        $this->createSupportReports();
        $this->createActivityReports();
        $this->createFinancialReports();
        $this->createTaskReports();

        $this->command->info('Default reports created successfully!');
    }

    private function createReport(array $data): Report
    {
        return Report::firstOrCreate(
            ['name' => $data['name'], 'module_id' => $data['module_id']],
            $data
        );
    }

    private function createSalesReports(): void
    {
        $dealsModule = Module::where('api_name', 'deals')->first();
        if (!$dealsModule) {
            $this->command->warn('  - Deals module not found, skipping sales reports');
            return;
        }

        // Sales Pipeline Report (Funnel)
        $this->createReport([
            'name' => 'Sales Pipeline',
            'description' => 'Visual funnel of deal values by stage',
            'module_id' => $dealsModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'funnel',
            'is_public' => true,
            'is_favorite' => true,
            'filters' => [
                ['field' => 'stage', 'operator' => 'not_in', 'value' => ['closed_won', 'closed_lost']]
            ],
            'grouping' => [
                ['field' => 'stage', 'order' => 'display_order']
            ],
            'aggregations' => [
                ['function' => 'sum', 'field' => 'amount', 'alias' => 'total_value'],
                ['function' => 'count', 'field' => '*', 'alias' => 'deal_count']
            ],
            'config' => ['folder' => 'Sales'],
        ]);

        // Monthly Revenue Report
        $this->createReport([
            'name' => 'Monthly Revenue',
            'description' => 'Monthly closed revenue for current year',
            'module_id' => $dealsModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'bar',
            'is_public' => true,
            'filters' => [
                ['field' => 'stage', 'operator' => 'equals', 'value' => 'closed_won']
            ],
            'grouping' => [
                ['field' => 'close_date', 'interval' => 'month']
            ],
            'aggregations' => [
                ['function' => 'sum', 'field' => 'amount', 'alias' => 'revenue']
            ],
            'date_range' => ['field' => 'close_date', 'range' => 'this_year'],
            'config' => ['folder' => 'Sales'],
        ]);

        // Top Deals Report
        $this->createReport([
            'name' => 'Top Deals',
            'description' => 'Top 10 open deals by value',
            'module_id' => $dealsModule->id,
            'user_id' => 1,
            'type' => 'table',
            'is_public' => true,
            'filters' => [
                ['field' => 'stage', 'operator' => 'not_in', 'value' => ['closed_won', 'closed_lost']]
            ],
            'sorting' => [
                ['field' => 'amount', 'direction' => 'desc']
            ],
            'config' => [
                'folder' => 'Sales',
                'columns' => ['name', 'organization_id', 'amount', 'stage', 'close_date', 'assigned_to'],
                'limit' => 10
            ],
        ]);

        // Sales by Rep Report
        $this->createReport([
            'name' => 'Sales by Rep',
            'description' => 'Closed revenue by sales rep this quarter',
            'module_id' => $dealsModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'bar',
            'is_public' => true,
            'filters' => [
                ['field' => 'stage', 'operator' => 'equals', 'value' => 'closed_won']
            ],
            'grouping' => [
                ['field' => 'assigned_to']
            ],
            'aggregations' => [
                ['function' => 'sum', 'field' => 'amount', 'alias' => 'revenue'],
                ['function' => 'count', 'field' => '*', 'alias' => 'deals_won']
            ],
            'date_range' => ['field' => 'close_date', 'range' => 'this_quarter'],
            'config' => ['folder' => 'Sales'],
        ]);

        // Win/Loss Report
        $this->createReport([
            'name' => 'Win/Loss Analysis',
            'description' => 'Win rate analysis for closed deals',
            'module_id' => $dealsModule->id,
            'user_id' => 1,
            'type' => 'summary',
            'is_public' => true,
            'filters' => [
                ['field' => 'stage', 'operator' => 'in', 'value' => ['closed_won', 'closed_lost']]
            ],
            'grouping' => [
                ['field' => 'stage']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'deal_count'],
                ['function' => 'sum', 'field' => 'amount', 'alias' => 'total_value']
            ],
            'date_range' => ['field' => 'close_date', 'range' => 'this_quarter'],
            'config' => ['folder' => 'Sales'],
        ]);

        // Deal Source Analysis
        $this->createReport([
            'name' => 'Deal Source Analysis',
            'description' => 'Revenue by lead source',
            'module_id' => $dealsModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'pie',
            'is_public' => true,
            'filters' => [
                ['field' => 'stage', 'operator' => 'equals', 'value' => 'closed_won']
            ],
            'grouping' => [
                ['field' => 'source']
            ],
            'aggregations' => [
                ['function' => 'sum', 'field' => 'amount', 'alias' => 'revenue']
            ],
            'date_range' => ['field' => 'close_date', 'range' => 'this_year'],
            'config' => ['folder' => 'Sales'],
        ]);

        $this->command->info('  - Created 6 sales reports');
    }

    private function createCustomerReports(): void
    {
        $contactsModule = Module::where('api_name', 'contacts')->first();
        $orgsModule = Module::where('api_name', 'organizations')->first();

        if ($contactsModule) {
            // Contacts by Status
            $this->createReport([
                'name' => 'Contacts by Status',
                'description' => 'Distribution of contacts by status',
                'module_id' => $contactsModule->id,
                'user_id' => 1,
                'type' => 'chart',
                'chart_type' => 'pie',
                'is_public' => true,
                'grouping' => [
                    ['field' => 'status']
                ],
                'aggregations' => [
                    ['function' => 'count', 'field' => '*', 'alias' => 'count']
                ],
                'config' => ['folder' => 'Customers'],
            ]);

            // New Contacts Report
            $this->createReport([
                'name' => 'New Contacts Trend',
                'description' => 'New contacts added over time',
                'module_id' => $contactsModule->id,
                'user_id' => 1,
                'type' => 'chart',
                'chart_type' => 'line',
                'is_public' => true,
                'grouping' => [
                    ['field' => 'created_at', 'interval' => 'week']
                ],
                'aggregations' => [
                    ['function' => 'count', 'field' => '*', 'alias' => 'new_contacts']
                ],
                'date_range' => ['field' => 'created_at', 'range' => 'last_90_days'],
                'config' => ['folder' => 'Customers'],
            ]);

            // Contacts by Lead Source
            $this->createReport([
                'name' => 'Contacts by Lead Source',
                'description' => 'Where contacts are coming from',
                'module_id' => $contactsModule->id,
                'user_id' => 1,
                'type' => 'chart',
                'chart_type' => 'doughnut',
                'is_public' => true,
                'grouping' => [
                    ['field' => 'lead_source']
                ],
                'aggregations' => [
                    ['function' => 'count', 'field' => '*', 'alias' => 'count']
                ],
                'config' => ['folder' => 'Customers'],
            ]);
        }

        if ($orgsModule) {
            // Organizations by Industry
            $this->createReport([
                'name' => 'Organizations by Industry',
                'description' => 'Organization count by industry',
                'module_id' => $orgsModule->id,
                'user_id' => 1,
                'type' => 'chart',
                'chart_type' => 'bar',
                'is_public' => true,
                'grouping' => [
                    ['field' => 'industry']
                ],
                'aggregations' => [
                    ['function' => 'count', 'field' => '*', 'alias' => 'count']
                ],
                'config' => ['folder' => 'Customers'],
            ]);
        }

        $this->command->info('  - Created 4 customer reports');
    }

    private function createSupportReports(): void
    {
        $casesModule = Module::where('api_name', 'cases')->first();
        if (!$casesModule) {
            $this->command->warn('  - Cases module not found, skipping support reports');
            return;
        }

        // Open Cases by Priority
        $this->createReport([
            'name' => 'Open Cases by Priority',
            'description' => 'Open case distribution by priority',
            'module_id' => $casesModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'bar',
            'is_public' => true,
            'filters' => [
                ['field' => 'status', 'operator' => 'not_in', 'value' => ['resolved', 'closed']]
            ],
            'grouping' => [
                ['field' => 'priority']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'case_count']
            ],
            'config' => ['folder' => 'Support'],
        ]);

        // Cases by Status
        $this->createReport([
            'name' => 'Cases by Status',
            'description' => 'Current case status distribution',
            'module_id' => $casesModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'pie',
            'is_public' => true,
            'grouping' => [
                ['field' => 'status']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'case_count']
            ],
            'config' => ['folder' => 'Support'],
        ]);

        // Case Trend Report
        $this->createReport([
            'name' => 'Case Volume Trend',
            'description' => 'Case volume over time',
            'module_id' => $casesModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'line',
            'is_public' => true,
            'grouping' => [
                ['field' => 'created_at', 'interval' => 'week']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'new_cases']
            ],
            'date_range' => ['field' => 'created_at', 'range' => 'last_90_days'],
            'config' => ['folder' => 'Support'],
        ]);

        // Overdue Cases
        $this->createReport([
            'name' => 'Overdue Cases',
            'description' => 'Cases past SLA deadline',
            'module_id' => $casesModule->id,
            'user_id' => 1,
            'type' => 'table',
            'is_public' => true,
            'filters' => [
                ['field' => 'sla_due_date', 'operator' => 'less_than', 'value' => 'now'],
                ['field' => 'status', 'operator' => 'not_in', 'value' => ['resolved', 'closed']]
            ],
            'sorting' => [
                ['field' => 'sla_due_date', 'direction' => 'asc']
            ],
            'config' => [
                'folder' => 'Support',
                'columns' => ['case_number', 'subject', 'priority', 'contact_id', 'sla_due_date', 'assigned_to']
            ],
        ]);

        // Cases by Type
        $this->createReport([
            'name' => 'Cases by Type',
            'description' => 'Case distribution by type',
            'module_id' => $casesModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'doughnut',
            'is_public' => true,
            'grouping' => [
                ['field' => 'type']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'case_count']
            ],
            'config' => ['folder' => 'Support'],
        ]);

        $this->command->info('  - Created 5 support reports');
    }

    private function createActivityReports(): void
    {
        $activitiesModule = Module::where('api_name', 'activities')->first();
        if (!$activitiesModule) {
            $this->command->warn('  - Activities module not found, skipping activity reports');
            return;
        }

        // Activity Summary
        $this->createReport([
            'name' => 'Activity Summary',
            'description' => 'Activity breakdown by type and outcome',
            'module_id' => $activitiesModule->id,
            'user_id' => 1,
            'type' => 'summary',
            'is_public' => true,
            'grouping' => [
                ['field' => 'type'],
                ['field' => 'outcome']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'activity_count']
            ],
            'date_range' => ['field' => 'start_datetime', 'range' => 'this_month'],
            'config' => ['folder' => 'Activities'],
        ]);

        // Activities by Rep
        $this->createReport([
            'name' => 'Activities by Rep',
            'description' => 'Activity count by team member',
            'module_id' => $activitiesModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'bar',
            'is_public' => true,
            'grouping' => [
                ['field' => 'assigned_to']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'activity_count']
            ],
            'date_range' => ['field' => 'start_datetime', 'range' => 'this_week'],
            'config' => ['folder' => 'Activities'],
        ]);

        // Call Outcomes
        $this->createReport([
            'name' => 'Call Outcomes',
            'description' => 'Call outcome distribution',
            'module_id' => $activitiesModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'doughnut',
            'is_public' => true,
            'filters' => [
                ['field' => 'type', 'operator' => 'equals', 'value' => 'call']
            ],
            'grouping' => [
                ['field' => 'outcome']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'call_count']
            ],
            'date_range' => ['field' => 'start_datetime', 'range' => 'this_month'],
            'config' => ['folder' => 'Activities'],
        ]);

        // Activity Trend
        $this->createReport([
            'name' => 'Activity Trend',
            'description' => 'Activity volume over time',
            'module_id' => $activitiesModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'area',
            'is_public' => true,
            'grouping' => [
                ['field' => 'start_datetime', 'interval' => 'day']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'activities']
            ],
            'date_range' => ['field' => 'start_datetime', 'range' => 'last_30_days'],
            'config' => ['folder' => 'Activities'],
        ]);

        $this->command->info('  - Created 4 activity reports');
    }

    private function createFinancialReports(): void
    {
        $invoicesModule = Module::where('api_name', 'invoices')->first();
        $quotesModule = Module::where('api_name', 'quotes')->first();

        if ($invoicesModule) {
            // Invoice Aging Report
            $this->createReport([
                'name' => 'Invoice Aging',
                'description' => 'Outstanding invoices by age',
                'module_id' => $invoicesModule->id,
                'user_id' => 1,
                'type' => 'chart',
                'chart_type' => 'bar',
                'is_public' => true,
                'filters' => [
                    ['field' => 'status', 'operator' => 'in', 'value' => ['sent', 'overdue']]
                ],
                'grouping' => [
                    ['field' => 'due_date', 'interval' => 'aging_bucket']
                ],
                'aggregations' => [
                    ['function' => 'sum', 'field' => 'balance_due', 'alias' => 'outstanding'],
                    ['function' => 'count', 'field' => '*', 'alias' => 'invoice_count']
                ],
                'config' => ['folder' => 'Financial'],
            ]);

            // Revenue by Month
            $this->createReport([
                'name' => 'Collected Revenue',
                'description' => 'Monthly collected revenue',
                'module_id' => $invoicesModule->id,
                'user_id' => 1,
                'type' => 'chart',
                'chart_type' => 'line',
                'is_public' => true,
                'filters' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'paid']
                ],
                'grouping' => [
                    ['field' => 'payment_date', 'interval' => 'month']
                ],
                'aggregations' => [
                    ['function' => 'sum', 'field' => 'amount_paid', 'alias' => 'collected']
                ],
                'date_range' => ['field' => 'payment_date', 'range' => 'this_year'],
                'config' => ['folder' => 'Financial'],
            ]);

            // Unpaid Invoices
            $this->createReport([
                'name' => 'Unpaid Invoices',
                'description' => 'All outstanding invoices',
                'module_id' => $invoicesModule->id,
                'user_id' => 1,
                'type' => 'table',
                'is_public' => true,
                'filters' => [
                    ['field' => 'status', 'operator' => 'in', 'value' => ['sent', 'overdue']]
                ],
                'sorting' => [
                    ['field' => 'due_date', 'direction' => 'asc']
                ],
                'config' => [
                    'folder' => 'Financial',
                    'columns' => ['invoice_number', 'organization_id', 'total', 'balance_due', 'due_date', 'status']
                ],
            ]);

            // Invoices by Status
            $this->createReport([
                'name' => 'Invoices by Status',
                'description' => 'Invoice distribution by status',
                'module_id' => $invoicesModule->id,
                'user_id' => 1,
                'type' => 'chart',
                'chart_type' => 'pie',
                'is_public' => true,
                'grouping' => [
                    ['field' => 'status']
                ],
                'aggregations' => [
                    ['function' => 'sum', 'field' => 'total', 'alias' => 'total_value'],
                    ['function' => 'count', 'field' => '*', 'alias' => 'invoice_count']
                ],
                'config' => ['folder' => 'Financial'],
            ]);
        }

        if ($quotesModule) {
            // Quote Conversion Report
            $this->createReport([
                'name' => 'Quote Conversion',
                'description' => 'Quote acceptance rate',
                'module_id' => $quotesModule->id,
                'user_id' => 1,
                'type' => 'summary',
                'is_public' => true,
                'grouping' => [
                    ['field' => 'status']
                ],
                'aggregations' => [
                    ['function' => 'count', 'field' => '*', 'alias' => 'quote_count'],
                    ['function' => 'sum', 'field' => 'total', 'alias' => 'total_value']
                ],
                'date_range' => ['field' => 'quote_date', 'range' => 'this_quarter'],
                'config' => ['folder' => 'Financial'],
            ]);

            // Expiring Quotes
            $this->createReport([
                'name' => 'Expiring Quotes',
                'description' => 'Quotes expiring soon',
                'module_id' => $quotesModule->id,
                'user_id' => 1,
                'type' => 'table',
                'is_public' => true,
                'filters' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'sent'],
                    ['field' => 'valid_until', 'operator' => 'less_than', 'value' => '+7 days']
                ],
                'sorting' => [
                    ['field' => 'valid_until', 'direction' => 'asc']
                ],
                'config' => [
                    'folder' => 'Financial',
                    'columns' => ['quote_number', 'subject', 'organization_id', 'total', 'valid_until', 'assigned_to']
                ],
            ]);
        }

        $this->command->info('  - Created 6 financial reports');
    }

    private function createTaskReports(): void
    {
        $tasksModule = Module::where('api_name', 'tasks')->first();
        if (!$tasksModule) {
            $this->command->warn('  - Tasks module not found, skipping task reports');
            return;
        }

        // Overdue Tasks by Owner
        $this->createReport([
            'name' => 'Overdue Tasks',
            'description' => 'Overdue task count by team member',
            'module_id' => $tasksModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'bar',
            'is_public' => true,
            'filters' => [
                ['field' => 'due_date', 'operator' => 'less_than', 'value' => 'now'],
                ['field' => 'status', 'operator' => 'not_in', 'value' => ['completed', 'deferred']]
            ],
            'grouping' => [
                ['field' => 'assigned_to']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'overdue_count']
            ],
            'config' => ['folder' => 'Tasks'],
        ]);

        // Tasks by Status
        $this->createReport([
            'name' => 'Tasks by Status',
            'description' => 'Task distribution by status',
            'module_id' => $tasksModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'pie',
            'is_public' => true,
            'grouping' => [
                ['field' => 'status']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'task_count']
            ],
            'config' => ['folder' => 'Tasks'],
        ]);

        // Tasks by Priority
        $this->createReport([
            'name' => 'Tasks by Priority',
            'description' => 'Open task distribution by priority',
            'module_id' => $tasksModule->id,
            'user_id' => 1,
            'type' => 'chart',
            'chart_type' => 'bar',
            'is_public' => true,
            'filters' => [
                ['field' => 'status', 'operator' => 'not_equals', 'value' => 'completed']
            ],
            'grouping' => [
                ['field' => 'priority']
            ],
            'aggregations' => [
                ['function' => 'count', 'field' => '*', 'alias' => 'task_count']
            ],
            'config' => ['folder' => 'Tasks'],
        ]);

        // My Tasks Due This Week
        $this->createReport([
            'name' => 'Tasks Due This Week',
            'description' => 'Tasks due in the current week',
            'module_id' => $tasksModule->id,
            'user_id' => 1,
            'type' => 'table',
            'is_public' => true,
            'filters' => [
                ['field' => 'status', 'operator' => 'not_equals', 'value' => 'completed']
            ],
            'sorting' => [
                ['field' => 'due_date', 'direction' => 'asc'],
                ['field' => 'priority', 'direction' => 'desc']
            ],
            'date_range' => ['field' => 'due_date', 'range' => 'this_week'],
            'config' => [
                'folder' => 'Tasks',
                'columns' => ['subject', 'priority', 'due_date', 'status', 'assigned_to']
            ],
        ]);

        $this->command->info('  - Created 4 task reports');
    }
}
