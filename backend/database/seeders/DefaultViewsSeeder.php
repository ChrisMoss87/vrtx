<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ModuleView;
use Illuminate\Database\Seeder;

/**
 * Seeds the default views (saved table filters) for a new tenant.
 */
class DefaultViewsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating default views...');

        $this->createContactsViews();
        $this->createOrganizationsViews();
        $this->createDealsViews();
        $this->createTasksViews();
        $this->createActivitiesViews();
        $this->createCasesViews();
        $this->createInvoicesViews();
        $this->createQuotesViews();
        $this->createProductsViews();
        $this->createEventsViews();

        $this->command->info('Default views created successfully!');
    }

    private function createContactsViews(): void
    {
        $module = Module::where('api_name', 'contacts')->first();
        if (!$module) return;

        $views = [
            [
                'name' => 'All Contacts',
                'is_default' => true,
                'columns' => ['first_name', 'last_name', 'email', 'phone', 'organization_id', 'status'],
                'sorting' => [['field' => 'last_name', 'direction' => 'asc']],
            ],
            [
                'name' => 'My Contacts',
                'is_default' => false,
                'filters' => [['field' => 'assigned_to', 'operator' => 'equals', 'value' => '$current_user']],
                'columns' => ['first_name', 'last_name', 'email', 'phone', 'status'],
                'sorting' => [['field' => 'created_at', 'direction' => 'desc']],
            ],
            [
                'name' => 'Leads',
                'is_default' => false,
                'filters' => [['field' => 'status', 'operator' => 'equals', 'value' => 'lead']],
                'columns' => ['first_name', 'last_name', 'email', 'phone', 'lead_source', 'assigned_to'],
                'sorting' => [['field' => 'created_at', 'direction' => 'desc']],
            ],
            [
                'name' => 'Customers',
                'is_default' => false,
                'filters' => [['field' => 'status', 'operator' => 'equals', 'value' => 'customer']],
                'columns' => ['first_name', 'last_name', 'email', 'organization_id', 'phone'],
                'sorting' => [['field' => 'last_name', 'direction' => 'asc']],
            ],
            [
                'name' => 'Recently Added',
                'is_default' => false,
                'filters' => [['field' => 'created_at', 'operator' => 'within', 'value' => 'last_7_days']],
                'columns' => ['first_name', 'last_name', 'email', 'status', 'created_at'],
                'sorting' => [['field' => 'created_at', 'direction' => 'desc']],
            ],
        ];

        $this->createViews($module, $views);
        $this->command->info('  - Created ' . count($views) . ' contact views');
    }

    private function createOrganizationsViews(): void
    {
        $module = Module::where('api_name', 'organizations')->first();
        if (!$module) return;

        $views = [
            [
                'name' => 'All Organizations',
                'is_default' => true,
                'columns' => ['name', 'industry', 'type', 'phone', 'website'],
                'sorting' => [['field' => 'name', 'direction' => 'asc']],
            ],
            [
                'name' => 'My Accounts',
                'is_default' => false,
                'filters' => [['field' => 'assigned_to', 'operator' => 'equals', 'value' => '$current_user']],
                'columns' => ['name', 'type', 'industry', 'phone'],
                'sorting' => [['field' => 'name', 'direction' => 'asc']],
            ],
            [
                'name' => 'Customers',
                'is_default' => false,
                'filters' => [['field' => 'type', 'operator' => 'equals', 'value' => 'customer']],
                'columns' => ['name', 'industry', 'annual_revenue', 'phone'],
                'sorting' => [['field' => 'name', 'direction' => 'asc']],
            ],
            [
                'name' => 'Prospects',
                'is_default' => false,
                'filters' => [['field' => 'type', 'operator' => 'equals', 'value' => 'prospect']],
                'columns' => ['name', 'industry', 'assigned_to'],
                'sorting' => [['field' => 'created_at', 'direction' => 'desc']],
            ],
        ];

        $this->createViews($module, $views);
        $this->command->info('  - Created ' . count($views) . ' organization views');
    }

    private function createDealsViews(): void
    {
        $module = Module::where('api_name', 'deals')->first();
        if (!$module) return;

        $views = [
            [
                'name' => 'All Deals',
                'is_default' => true,
                'columns' => ['name', 'organization_id', 'amount', 'stage', 'close_date'],
                'sorting' => [['field' => 'close_date', 'direction' => 'asc']],
            ],
            [
                'name' => 'My Open Deals',
                'is_default' => false,
                'filters' => [
                    ['field' => 'assigned_to', 'operator' => 'equals', 'value' => '$current_user'],
                    ['field' => 'stage', 'operator' => 'not_in', 'value' => ['closed_won', 'closed_lost']]
                ],
                'columns' => ['name', 'amount', 'stage', 'close_date'],
                'sorting' => [['field' => 'close_date', 'direction' => 'asc']],
            ],
            [
                'name' => 'Closing This Month',
                'is_default' => false,
                'filters' => [
                    ['field' => 'close_date', 'operator' => 'within', 'value' => 'this_month'],
                    ['field' => 'stage', 'operator' => 'not_in', 'value' => ['closed_won', 'closed_lost']]
                ],
                'columns' => ['name', 'organization_id', 'amount', 'stage', 'probability'],
                'sorting' => [['field' => 'amount', 'direction' => 'desc']],
            ],
            [
                'name' => 'Won Deals',
                'is_default' => false,
                'filters' => [['field' => 'stage', 'operator' => 'equals', 'value' => 'closed_won']],
                'columns' => ['name', 'organization_id', 'amount', 'close_date'],
                'sorting' => [['field' => 'close_date', 'direction' => 'desc']],
            ],
            [
                'name' => 'High Value',
                'is_default' => false,
                'filters' => [
                    ['field' => 'amount', 'operator' => 'greater_than', 'value' => 10000],
                    ['field' => 'stage', 'operator' => 'not_in', 'value' => ['closed_won', 'closed_lost']]
                ],
                'columns' => ['name', 'organization_id', 'amount', 'stage', 'assigned_to'],
                'sorting' => [['field' => 'amount', 'direction' => 'desc']],
            ],
        ];

        $this->createViews($module, $views);
        $this->command->info('  - Created ' . count($views) . ' deal views');
    }

    private function createTasksViews(): void
    {
        $module = Module::where('api_name', 'tasks')->first();
        if (!$module) return;

        $views = [
            [
                'name' => 'All Tasks',
                'is_default' => true,
                'columns' => ['subject', 'status', 'priority', 'due_date', 'assigned_to'],
                'sorting' => [['field' => 'due_date', 'direction' => 'asc']],
            ],
            [
                'name' => 'My Tasks',
                'is_default' => false,
                'filters' => [['field' => 'assigned_to', 'operator' => 'equals', 'value' => '$current_user']],
                'columns' => ['subject', 'status', 'priority', 'due_date'],
                'sorting' => [['field' => 'due_date', 'direction' => 'asc']],
            ],
            [
                'name' => 'Today',
                'is_default' => false,
                'filters' => [['field' => 'due_date', 'operator' => 'equals', 'value' => 'today']],
                'columns' => ['subject', 'status', 'priority', 'assigned_to'],
                'sorting' => [['field' => 'priority', 'direction' => 'desc']],
            ],
            [
                'name' => 'Overdue',
                'is_default' => false,
                'filters' => [
                    ['field' => 'due_date', 'operator' => 'less_than', 'value' => 'today'],
                    ['field' => 'status', 'operator' => 'not_equals', 'value' => 'completed']
                ],
                'columns' => ['subject', 'priority', 'due_date', 'assigned_to'],
                'sorting' => [['field' => 'due_date', 'direction' => 'asc']],
            ],
            [
                'name' => 'High Priority',
                'is_default' => false,
                'filters' => [
                    ['field' => 'priority', 'operator' => 'in', 'value' => ['high', 'urgent']],
                    ['field' => 'status', 'operator' => 'not_equals', 'value' => 'completed']
                ],
                'columns' => ['subject', 'status', 'due_date', 'assigned_to'],
                'sorting' => [['field' => 'due_date', 'direction' => 'asc']],
            ],
        ];

        $this->createViews($module, $views);
        $this->command->info('  - Created ' . count($views) . ' task views');
    }

    private function createActivitiesViews(): void
    {
        $module = Module::where('api_name', 'activities')->first();
        if (!$module) return;

        $views = [
            [
                'name' => 'All Activities',
                'is_default' => true,
                'columns' => ['subject', 'type', 'start_datetime', 'contact_id', 'assigned_to'],
                'sorting' => [['field' => 'start_datetime', 'direction' => 'desc']],
            ],
            [
                'name' => 'My Activities',
                'is_default' => false,
                'filters' => [['field' => 'assigned_to', 'operator' => 'equals', 'value' => '$current_user']],
                'columns' => ['subject', 'type', 'start_datetime', 'contact_id'],
                'sorting' => [['field' => 'start_datetime', 'direction' => 'desc']],
            ],
            [
                'name' => 'Today',
                'is_default' => false,
                'filters' => [['field' => 'start_datetime', 'operator' => 'within', 'value' => 'today']],
                'columns' => ['subject', 'type', 'start_datetime', 'contact_id', 'outcome'],
                'sorting' => [['field' => 'start_datetime', 'direction' => 'asc']],
            ],
            [
                'name' => 'This Week Calls',
                'is_default' => false,
                'filters' => [
                    ['field' => 'type', 'operator' => 'equals', 'value' => 'call'],
                    ['field' => 'start_datetime', 'operator' => 'within', 'value' => 'this_week']
                ],
                'columns' => ['subject', 'start_datetime', 'contact_id', 'outcome'],
                'sorting' => [['field' => 'start_datetime', 'direction' => 'desc']],
            ],
            [
                'name' => 'Recent Meetings',
                'is_default' => false,
                'filters' => [['field' => 'type', 'operator' => 'equals', 'value' => 'meeting']],
                'columns' => ['subject', 'start_datetime', 'contact_id', 'organization_id'],
                'sorting' => [['field' => 'start_datetime', 'direction' => 'desc']],
            ],
        ];

        $this->createViews($module, $views);
        $this->command->info('  - Created ' . count($views) . ' activity views');
    }

    private function createCasesViews(): void
    {
        $module = Module::where('api_name', 'cases')->first();
        if (!$module) return;

        $views = [
            [
                'name' => 'All Cases',
                'is_default' => true,
                'columns' => ['case_number', 'subject', 'status', 'priority', 'contact_id'],
                'sorting' => [['field' => 'created_at', 'direction' => 'desc']],
            ],
            [
                'name' => 'My Cases',
                'is_default' => false,
                'filters' => [['field' => 'assigned_to', 'operator' => 'equals', 'value' => '$current_user']],
                'columns' => ['case_number', 'subject', 'status', 'priority', 'sla_due_date'],
                'sorting' => [['field' => 'priority', 'direction' => 'desc']],
            ],
            [
                'name' => 'Open Cases',
                'is_default' => false,
                'filters' => [['field' => 'status', 'operator' => 'not_in', 'value' => ['resolved', 'closed']]],
                'columns' => ['case_number', 'subject', 'priority', 'contact_id', 'assigned_to'],
                'sorting' => [['field' => 'created_at', 'direction' => 'desc']],
            ],
            [
                'name' => 'Critical',
                'is_default' => false,
                'filters' => [
                    ['field' => 'priority', 'operator' => 'equals', 'value' => 'critical'],
                    ['field' => 'status', 'operator' => 'not_in', 'value' => ['resolved', 'closed']]
                ],
                'columns' => ['case_number', 'subject', 'contact_id', 'sla_due_date'],
                'sorting' => [['field' => 'sla_due_date', 'direction' => 'asc']],
            ],
            [
                'name' => 'Escalated',
                'is_default' => false,
                'filters' => [['field' => 'escalated', 'operator' => 'equals', 'value' => true]],
                'columns' => ['case_number', 'subject', 'priority', 'escalation_date'],
                'sorting' => [['field' => 'escalation_date', 'direction' => 'desc']],
            ],
        ];

        $this->createViews($module, $views);
        $this->command->info('  - Created ' . count($views) . ' case views');
    }

    private function createInvoicesViews(): void
    {
        $module = Module::where('api_name', 'invoices')->first();
        if (!$module) return;

        $views = [
            [
                'name' => 'All Invoices',
                'is_default' => true,
                'columns' => ['invoice_number', 'organization_id', 'total', 'status', 'due_date'],
                'sorting' => [['field' => 'invoice_date', 'direction' => 'desc']],
            ],
            [
                'name' => 'Unpaid',
                'is_default' => false,
                'filters' => [['field' => 'status', 'operator' => 'in', 'value' => ['sent', 'overdue']]],
                'columns' => ['invoice_number', 'organization_id', 'total', 'balance_due', 'due_date'],
                'sorting' => [['field' => 'due_date', 'direction' => 'asc']],
            ],
            [
                'name' => 'Overdue',
                'is_default' => false,
                'filters' => [['field' => 'status', 'operator' => 'equals', 'value' => 'overdue']],
                'columns' => ['invoice_number', 'organization_id', 'balance_due', 'due_date'],
                'sorting' => [['field' => 'due_date', 'direction' => 'asc']],
            ],
            [
                'name' => 'This Month',
                'is_default' => false,
                'filters' => [['field' => 'invoice_date', 'operator' => 'within', 'value' => 'this_month']],
                'columns' => ['invoice_number', 'organization_id', 'total', 'status'],
                'sorting' => [['field' => 'invoice_date', 'direction' => 'desc']],
            ],
            [
                'name' => 'Paid',
                'is_default' => false,
                'filters' => [['field' => 'status', 'operator' => 'equals', 'value' => 'paid']],
                'columns' => ['invoice_number', 'organization_id', 'total', 'payment_date'],
                'sorting' => [['field' => 'payment_date', 'direction' => 'desc']],
            ],
        ];

        $this->createViews($module, $views);
        $this->command->info('  - Created ' . count($views) . ' invoice views');
    }

    private function createQuotesViews(): void
    {
        $module = Module::where('api_name', 'quotes')->first();
        if (!$module) return;

        $views = [
            [
                'name' => 'All Quotes',
                'is_default' => true,
                'columns' => ['quote_number', 'subject', 'organization_id', 'total', 'status'],
                'sorting' => [['field' => 'quote_date', 'direction' => 'desc']],
            ],
            [
                'name' => 'My Quotes',
                'is_default' => false,
                'filters' => [['field' => 'assigned_to', 'operator' => 'equals', 'value' => '$current_user']],
                'columns' => ['quote_number', 'subject', 'total', 'status', 'valid_until'],
                'sorting' => [['field' => 'quote_date', 'direction' => 'desc']],
            ],
            [
                'name' => 'Pending',
                'is_default' => false,
                'filters' => [['field' => 'status', 'operator' => 'equals', 'value' => 'sent']],
                'columns' => ['quote_number', 'subject', 'organization_id', 'total', 'valid_until'],
                'sorting' => [['field' => 'valid_until', 'direction' => 'asc']],
            ],
            [
                'name' => 'Expiring Soon',
                'is_default' => false,
                'filters' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'sent'],
                    ['field' => 'valid_until', 'operator' => 'within', 'value' => 'next_7_days']
                ],
                'columns' => ['quote_number', 'subject', 'total', 'valid_until', 'assigned_to'],
                'sorting' => [['field' => 'valid_until', 'direction' => 'asc']],
            ],
        ];

        $this->createViews($module, $views);
        $this->command->info('  - Created ' . count($views) . ' quote views');
    }

    private function createProductsViews(): void
    {
        $module = Module::where('api_name', 'products')->first();
        if (!$module) return;

        $views = [
            [
                'name' => 'All Products',
                'is_default' => true,
                'columns' => ['name', 'sku', 'category', 'unit_price', 'quantity_in_stock'],
                'sorting' => [['field' => 'name', 'direction' => 'asc']],
            ],
            [
                'name' => 'Active Products',
                'is_default' => false,
                'filters' => [['field' => 'is_active', 'operator' => 'equals', 'value' => true]],
                'columns' => ['name', 'category', 'unit_price', 'quantity_in_stock'],
                'sorting' => [['field' => 'name', 'direction' => 'asc']],
            ],
            [
                'name' => 'Low Stock',
                'is_default' => false,
                'filters' => [
                    ['field' => 'quantity_in_stock', 'operator' => 'less_than_or_equal', 'value' => '$reorder_level'],
                    ['field' => 'is_active', 'operator' => 'equals', 'value' => true]
                ],
                'columns' => ['name', 'sku', 'quantity_in_stock', 'reorder_level', 'vendor'],
                'sorting' => [['field' => 'quantity_in_stock', 'direction' => 'asc']],
            ],
            [
                'name' => 'By Category',
                'is_default' => false,
                'columns' => ['name', 'category', 'unit_price', 'quantity_in_stock'],
                'sorting' => [['field' => 'category', 'direction' => 'asc'], ['field' => 'name', 'direction' => 'asc']],
                'grouping' => ['category'],
            ],
        ];

        $this->createViews($module, $views);
        $this->command->info('  - Created ' . count($views) . ' product views');
    }

    private function createEventsViews(): void
    {
        $module = Module::where('api_name', 'events')->first();
        if (!$module) return;

        $views = [
            [
                'name' => 'All Events',
                'is_default' => true,
                'columns' => ['title', 'event_type', 'start_datetime', 'end_datetime', 'location'],
                'sorting' => [['field' => 'start_datetime', 'direction' => 'asc']],
            ],
            [
                'name' => 'My Events',
                'is_default' => false,
                'filters' => [['field' => 'organizer_id', 'operator' => 'equals', 'value' => '$current_user']],
                'columns' => ['title', 'event_type', 'start_datetime', 'location'],
                'sorting' => [['field' => 'start_datetime', 'direction' => 'asc']],
            ],
            [
                'name' => 'Today',
                'is_default' => false,
                'filters' => [['field' => 'start_datetime', 'operator' => 'within', 'value' => 'today']],
                'columns' => ['title', 'event_type', 'start_datetime', 'end_datetime'],
                'sorting' => [['field' => 'start_datetime', 'direction' => 'asc']],
            ],
            [
                'name' => 'This Week',
                'is_default' => false,
                'filters' => [['field' => 'start_datetime', 'operator' => 'within', 'value' => 'this_week']],
                'columns' => ['title', 'event_type', 'start_datetime', 'location'],
                'sorting' => [['field' => 'start_datetime', 'direction' => 'asc']],
            ],
            [
                'name' => 'Upcoming',
                'is_default' => false,
                'filters' => [['field' => 'start_datetime', 'operator' => 'greater_than_or_equal', 'value' => 'today']],
                'columns' => ['title', 'event_type', 'start_datetime', 'location', 'contact_id'],
                'sorting' => [['field' => 'start_datetime', 'direction' => 'asc']],
            ],
        ];

        $this->createViews($module, $views);
        $this->command->info('  - Created ' . count($views) . ' event views');
    }

    private function createViews(Module $module, array $views): void
    {
        foreach ($views as $index => $viewData) {
            ModuleView::firstOrCreate(
                [
                    'module_id' => $module->id,
                    'name' => $viewData['name'],
                ],
                [
                    'user_id' => 1,
                    'is_default' => $viewData['is_default'] ?? false,
                    'is_shared' => true,
                    'display_order' => $index,
                    'filters' => $viewData['filters'] ?? [],
                    'sorting' => $viewData['sorting'] ?? [],
                    'column_visibility' => $this->buildColumnVisibility($viewData['columns'] ?? []),
                    'column_order' => $viewData['columns'] ?? [],
                ]
            );
        }
    }

    private function buildColumnVisibility(array $columns): array
    {
        $visibility = [];
        foreach ($columns as $column) {
            $visibility[$column] = true;
        }
        return $visibility;
    }
}
