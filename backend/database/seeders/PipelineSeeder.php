<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Services\PipelineFieldSyncService;
use Illuminate\Database\Seeder;

/**
 * Seeds demo pipelines and stages for tenant databases.
 *
 * This seeder should be run WITHIN tenant context using:
 * php artisan tenants:seed --class=PipelineSeeder
 */
class PipelineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = tenant('id');

        if (!$tenantId) {
            $this->command->error('This seeder must be run in tenant context!');
            $this->command->line('Use: php artisan tenants:seed --class=PipelineSeeder');
            return;
        }

        $this->command->info("Seeding pipelines for tenant: {$tenantId}");

        // Get the deals module (pipelines are typically used with deals)
        $dealsModule = Module::where('api_name', 'deals')->first();

        if (!$dealsModule) {
            $this->command->warn('Deals module not found. Creating pipelines without module association.');
        }

        $this->seedSalesPipeline($dealsModule);
        $this->seedPartnershipPipeline($dealsModule);
        $this->seedSupportPipeline();

        $this->command->newLine();
        $this->command->info('✓ Pipeline seeding complete!');
    }

    private function seedSalesPipeline(?Module $dealsModule): void
    {
        $this->command->info('Creating Sales Pipeline...');

        $pipeline = Pipeline::create([
            'name' => 'Sales Pipeline',
            'module_id' => $dealsModule?->id,
            'stage_field_api_name' => 'stage',
            'is_active' => true,
            'settings' => [
                'value_field' => 'amount',
                'title_field' => 'deal_name',
                'subtitle_field' => 'description',
            ],
        ]);

        $stages = [
            [
                'name' => 'Prospecting',
                'color' => '#64748b',
                'probability' => 10,
                'display_order' => 0,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Qualification',
                'color' => '#3b82f6',
                'probability' => 25,
                'display_order' => 1,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Proposal',
                'color' => '#8b5cf6',
                'probability' => 50,
                'display_order' => 2,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Negotiation',
                'color' => '#f59e0b',
                'probability' => 75,
                'display_order' => 3,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Closed Won',
                'color' => '#22c55e',
                'probability' => 100,
                'display_order' => 4,
                'is_won_stage' => true,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Closed Lost',
                'color' => '#ef4444',
                'probability' => 0,
                'display_order' => 5,
                'is_won_stage' => false,
                'is_lost_stage' => true,
            ],
        ];

        foreach ($stages as $stageData) {
            $pipeline->stages()->create($stageData);
        }

        // Sync field options with stages
        $syncService = app(PipelineFieldSyncService::class);
        $syncService->syncFieldOptionsFromStages($pipeline->fresh());

        $this->command->info('  ✓ Created Sales Pipeline with ' . count($stages) . ' stages');
    }

    private function seedPartnershipPipeline(?Module $dealsModule): void
    {
        $this->command->info('Creating Partnership Pipeline...');

        $pipeline = Pipeline::create([
            'name' => 'Partnership Pipeline',
            'module_id' => $dealsModule?->id,
            'stage_field_api_name' => 'stage',
            'is_active' => true,
            'settings' => [
                'value_field' => 'amount',
                'title_field' => 'deal_name',
            ],
        ]);

        $stages = [
            [
                'name' => 'Initial Contact',
                'color' => '#94a3b8',
                'probability' => 5,
                'display_order' => 0,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Evaluation',
                'color' => '#06b6d4',
                'probability' => 20,
                'display_order' => 1,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Due Diligence',
                'color' => '#0ea5e9',
                'probability' => 40,
                'display_order' => 2,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Terms Negotiation',
                'color' => '#a855f7',
                'probability' => 60,
                'display_order' => 3,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Legal Review',
                'color' => '#d946ef',
                'probability' => 80,
                'display_order' => 4,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Agreement Signed',
                'color' => '#22c55e',
                'probability' => 100,
                'display_order' => 5,
                'is_won_stage' => true,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Not Proceeding',
                'color' => '#f97316',
                'probability' => 0,
                'display_order' => 6,
                'is_won_stage' => false,
                'is_lost_stage' => true,
            ],
        ];

        foreach ($stages as $stageData) {
            $pipeline->stages()->create($stageData);
        }

        // Sync field options with stages
        $syncService = app(PipelineFieldSyncService::class);
        $syncService->syncFieldOptionsFromStages($pipeline->fresh());

        $this->command->info('  ✓ Created Partnership Pipeline with ' . count($stages) . ' stages');
    }

    private function seedSupportPipeline(): void
    {
        $this->command->info('Creating Support Ticket Pipeline...');

        // Get the cases module for support tickets
        $casesModule = Module::where('api_name', 'cases')->first();

        if (!$casesModule) {
            $this->command->warn('Cases module not found. Skipping Support Ticket Pipeline.');
            return;
        }

        $pipeline = Pipeline::create([
            'name' => 'Support Ticket Pipeline',
            'module_id' => $casesModule->id,
            'stage_field_api_name' => 'status',
            'is_active' => true,
            'settings' => [
                'title_field' => 'subject',
                'subtitle_field' => 'contact_name',
            ],
        ]);

        $stages = [
            [
                'name' => 'New',
                'color' => '#3b82f6',
                'probability' => 0,
                'display_order' => 0,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'In Progress',
                'color' => '#f59e0b',
                'probability' => 0,
                'display_order' => 1,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Waiting on Customer',
                'color' => '#8b5cf6',
                'probability' => 0,
                'display_order' => 2,
                'is_won_stage' => false,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Resolved',
                'color' => '#22c55e',
                'probability' => 100,
                'display_order' => 3,
                'is_won_stage' => true,
                'is_lost_stage' => false,
            ],
            [
                'name' => 'Closed - No Action',
                'color' => '#64748b',
                'probability' => 0,
                'display_order' => 4,
                'is_won_stage' => false,
                'is_lost_stage' => true,
            ],
        ];

        foreach ($stages as $stageData) {
            $pipeline->stages()->create($stageData);
        }

        // Sync field options with stages
        $syncService = app(PipelineFieldSyncService::class);
        $syncService->syncFieldOptionsFromStages($pipeline->fresh());

        $this->command->info('  ✓ Created Support Ticket Pipeline with ' . count($stages) . ' stages');
    }
}
