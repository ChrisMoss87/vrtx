<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Pipeline;
use App\Models\Stage;
use Illuminate\Database\Seeder;

/**
 * Seeds the default pipelines (kanban boards) for a new tenant.
 */
class DefaultPipelinesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating default pipelines...');

        $this->createSalesPipeline();
        $this->createSupportPipeline();
        $this->createTaskBoard();
        $this->createQuotePipeline();
        $this->createInvoicePipeline();

        $this->command->info('Default pipelines created successfully!');
    }

    private function createSalesPipeline(): void
    {
        $module = Module::where('api_name', 'deals')->first();
        if (!$module) {
            $this->command->warn('  - Deals module not found, skipping Sales Pipeline');
            return;
        }

        $pipeline = Pipeline::firstOrCreate(
            [
                'module_id' => $module->id,
                'name' => 'Sales Pipeline',
            ],
            [
                'stage_field_api_name' => 'stage',
                'is_active' => true,
                'settings' => [
                    'show_totals' => true,
                    'value_field' => 'amount',
                    'title_field' => 'name',
                    'subtitle_field' => 'organization_id',
                    'due_date_field' => 'close_date',
                ],
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );

        $stages = [
            ['name' => 'Prospecting', 'color' => '#6366f1', 'probability' => 10],
            ['name' => 'Qualification', 'color' => '#8b5cf6', 'probability' => 25],
            ['name' => 'Proposal', 'color' => '#a855f7', 'probability' => 50],
            ['name' => 'Negotiation', 'color' => '#d946ef', 'probability' => 75],
            ['name' => 'Closed Won', 'color' => '#22c55e', 'probability' => 100, 'is_won_stage' => true],
            ['name' => 'Closed Lost', 'color' => '#ef4444', 'probability' => 0, 'is_lost_stage' => true],
        ];

        $this->createStages($pipeline, $stages);
        $this->command->info('  - Created Sales Pipeline with ' . count($stages) . ' stages');
    }

    private function createSupportPipeline(): void
    {
        $module = Module::where('api_name', 'cases')->first();
        if (!$module) {
            $this->command->warn('  - Cases module not found, skipping Support Pipeline');
            return;
        }

        $pipeline = Pipeline::firstOrCreate(
            [
                'module_id' => $module->id,
                'name' => 'Support Pipeline',
            ],
            [
                'stage_field_api_name' => 'status',
                'is_active' => true,
                'settings' => [
                    'show_totals' => false,
                    'title_field' => 'subject',
                    'subtitle_field' => 'contact_id',
                    'due_date_field' => 'sla_due_date',
                ],
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );

        $stages = [
            ['name' => 'New', 'color' => '#6366f1', 'probability' => 0],
            ['name' => 'Open', 'color' => '#3b82f6', 'probability' => 10],
            ['name' => 'In Progress', 'color' => '#f59e0b', 'probability' => 50],
            ['name' => 'Waiting on Customer', 'color' => '#8b5cf6', 'probability' => 60],
            ['name' => 'Resolved', 'color' => '#22c55e', 'probability' => 100, 'is_won_stage' => true],
            ['name' => 'Closed', 'color' => '#6b7280', 'probability' => 100, 'is_won_stage' => true],
        ];

        $this->createStages($pipeline, $stages);
        $this->command->info('  - Created Support Pipeline with ' . count($stages) . ' stages');
    }

    private function createTaskBoard(): void
    {
        $module = Module::where('api_name', 'tasks')->first();
        if (!$module) {
            $this->command->warn('  - Tasks module not found, skipping Task Board');
            return;
        }

        $pipeline = Pipeline::firstOrCreate(
            [
                'module_id' => $module->id,
                'name' => 'Task Board',
            ],
            [
                'stage_field_api_name' => 'status',
                'is_active' => true,
                'settings' => [
                    'show_totals' => false,
                    'title_field' => 'subject',
                    'subtitle_field' => 'assigned_to',
                    'due_date_field' => 'due_date',
                ],
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );

        $stages = [
            ['name' => 'Not Started', 'color' => '#6b7280', 'probability' => 0],
            ['name' => 'In Progress', 'color' => '#3b82f6', 'probability' => 50],
            ['name' => 'Waiting', 'color' => '#f59e0b', 'probability' => 50],
            ['name' => 'Completed', 'color' => '#22c55e', 'probability' => 100, 'is_won_stage' => true],
            ['name' => 'Deferred', 'color' => '#8b5cf6', 'probability' => 0],
        ];

        $this->createStages($pipeline, $stages);
        $this->command->info('  - Created Task Board with ' . count($stages) . ' stages');
    }

    private function createQuotePipeline(): void
    {
        $module = Module::where('api_name', 'quotes')->first();
        if (!$module) {
            $this->command->warn('  - Quotes module not found, skipping Quote Pipeline');
            return;
        }

        $pipeline = Pipeline::firstOrCreate(
            [
                'module_id' => $module->id,
                'name' => 'Quote Pipeline',
            ],
            [
                'stage_field_api_name' => 'status',
                'is_active' => true,
                'settings' => [
                    'show_totals' => true,
                    'value_field' => 'total',
                    'title_field' => 'subject',
                    'subtitle_field' => 'organization_id',
                    'due_date_field' => 'valid_until',
                ],
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );

        $stages = [
            ['name' => 'Draft', 'color' => '#6b7280', 'probability' => 0],
            ['name' => 'Sent', 'color' => '#3b82f6', 'probability' => 50],
            ['name' => 'Accepted', 'color' => '#22c55e', 'probability' => 100, 'is_won_stage' => true],
            ['name' => 'Rejected', 'color' => '#ef4444', 'probability' => 0, 'is_lost_stage' => true],
            ['name' => 'Expired', 'color' => '#f59e0b', 'probability' => 0, 'is_lost_stage' => true],
        ];

        $this->createStages($pipeline, $stages);
        $this->command->info('  - Created Quote Pipeline with ' . count($stages) . ' stages');
    }

    private function createInvoicePipeline(): void
    {
        $module = Module::where('api_name', 'invoices')->first();
        if (!$module) {
            $this->command->warn('  - Invoices module not found, skipping Invoice Pipeline');
            return;
        }

        $pipeline = Pipeline::firstOrCreate(
            [
                'module_id' => $module->id,
                'name' => 'Invoice Pipeline',
            ],
            [
                'stage_field_api_name' => 'status',
                'is_active' => true,
                'settings' => [
                    'show_totals' => true,
                    'value_field' => 'total',
                    'title_field' => 'invoice_number',
                    'subtitle_field' => 'organization_id',
                    'due_date_field' => 'due_date',
                ],
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );

        $stages = [
            ['name' => 'Draft', 'color' => '#6b7280', 'probability' => 0],
            ['name' => 'Sent', 'color' => '#3b82f6', 'probability' => 50],
            ['name' => 'Overdue', 'color' => '#ef4444', 'probability' => 30],
            ['name' => 'Paid', 'color' => '#22c55e', 'probability' => 100, 'is_won_stage' => true],
            ['name' => 'Cancelled', 'color' => '#6b7280', 'probability' => 0, 'is_lost_stage' => true],
            ['name' => 'Refunded', 'color' => '#f59e0b', 'probability' => 0, 'is_lost_stage' => true],
        ];

        $this->createStages($pipeline, $stages);
        $this->command->info('  - Created Invoice Pipeline with ' . count($stages) . ' stages');
    }

    private function createStages(Pipeline $pipeline, array $stages): void
    {
        foreach ($stages as $index => $stageData) {
            Stage::firstOrCreate(
                [
                    'pipeline_id' => $pipeline->id,
                    'name' => $stageData['name'],
                ],
                [
                    'color' => $stageData['color'],
                    'probability' => $stageData['probability'],
                    'display_order' => $index,
                    'is_won_stage' => $stageData['is_won_stage'] ?? false,
                    'is_lost_stage' => $stageData['is_lost_stage'] ?? false,
                    'settings' => [],
                ]
            );
        }
    }
}
