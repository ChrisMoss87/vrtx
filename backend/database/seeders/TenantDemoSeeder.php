<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\WizardDraft;
use Illuminate\Database\Seeder;

/**
 * Seeds demo modules and records for tenant databases.
 *
 * This seeder should be run WITHIN tenant context using:
 * php artisan tenants:seed --class=TenantDemoSeeder
 */
class TenantDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = tenant('id');

        if (!$tenantId) {
            $this->command->error('This seeder must be run in tenant context!');
            $this->command->line('Use: php artisan tenants:seed --class=TenantDemoSeeder');
            return;
        }

        $this->command->info("Seeding demo data for tenant: {$tenantId}");
        $this->command->newLine();

        // First, seed users
        $this->call(TenantUserSeeder::class);
        $this->command->newLine();

        // Use the new default data seeder which includes:
        // - 11 core modules
        // - 5 pipelines
        // - 25+ reports
        // - 5 dashboards
        // - 45+ saved views
        $this->call(TenantDefaultDataSeeder::class);
        $this->command->newLine();

        // Seed sample data for demo purposes
        $this->call(SampleDataSeeder::class);
        $this->command->newLine();

        // Get the first user to use for wizard drafts
        $user = User::first();
        if ($user) {
            $this->seedWizardDrafts($user);
        }

        $this->command->newLine();
        $this->command->info('Demo data seeding complete!');
    }

    private function seedWizardDrafts(User $user): void
    {
        $this->command->info('Seeding Wizard Drafts...');

        // Create some sample wizard drafts
        $drafts = [
            [
                'user_id' => $user->id,
                'wizard_type' => 'module_creation',
                'name' => 'Tasks Module (In Progress)',
                'form_data' => [
                    'moduleName' => 'Tasks',
                    'singularName' => 'Task',
                    'description' => 'Task management and tracking',
                    'icon' => 'check-square',
                ],
                'steps_state' => [
                    ['id' => 'step-1', 'title' => 'Module Details', 'isValid' => true, 'isComplete' => true],
                    ['id' => 'step-2', 'title' => 'Fields', 'isValid' => false, 'isComplete' => false],
                    ['id' => 'step-3', 'title' => 'Settings', 'isValid' => false, 'isComplete' => false],
                ],
                'current_step_index' => 1,
                'expires_at' => now()->addDays(30),
            ],
            [
                'user_id' => $user->id,
                'wizard_type' => 'module_creation',
                'name' => 'Projects Module (Nearly Done)',
                'form_data' => [
                    'moduleName' => 'Projects',
                    'singularName' => 'Project',
                    'description' => 'Project management and tracking',
                    'icon' => 'folder',
                    'fields' => [
                        ['name' => 'Project Name', 'type' => 'text'],
                        ['name' => 'Budget', 'type' => 'currency'],
                        ['name' => 'Start Date', 'type' => 'date'],
                        ['name' => 'End Date', 'type' => 'date'],
                    ],
                ],
                'steps_state' => [
                    ['id' => 'step-1', 'title' => 'Module Details', 'isValid' => true, 'isComplete' => true],
                    ['id' => 'step-2', 'title' => 'Fields', 'isValid' => true, 'isComplete' => true],
                    ['id' => 'step-3', 'title' => 'Settings', 'isValid' => false, 'isComplete' => false],
                ],
                'current_step_index' => 2,
                'expires_at' => null, // Permanent draft
            ],
            [
                'user_id' => $user->id,
                'wizard_type' => 'record_creation',
                'reference_id' => '1', // Assuming contacts module ID is 1
                'name' => 'New Contact Draft',
                'form_data' => [
                    'first_name' => 'James',
                    'last_name' => 'Wilson',
                    'email' => 'james.wilson@example.com',
                ],
                'steps_state' => [
                    ['id' => 'step-1', 'title' => 'Basic Info', 'isValid' => true, 'isComplete' => true],
                    ['id' => 'step-2', 'title' => 'Contact Details', 'isValid' => false, 'isComplete' => false],
                    ['id' => 'step-3', 'title' => 'Review', 'isValid' => false, 'isComplete' => false],
                ],
                'current_step_index' => 1,
                'expires_at' => now()->addDays(7),
            ],
        ];

        foreach ($drafts as $draftData) {
            WizardDraft::create($draftData);
        }

        $this->command->info('  - Created ' . count($drafts) . ' wizard drafts');
    }
}
