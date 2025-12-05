<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ModuleRecord;
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

        // Then seed modules
        $this->call(ModuleDemoSeeder::class);

        // Get the first user to use as creator
        $user = User::first();

        if (!$user) {
            $this->command->warn('No users found. Please run TenantUserSeeder first.');
            return;
        }

        // Generate demo records for each module
        $this->seedContactsRecords($user);
        $this->seedProductsRecords($user);
        $this->seedDealsRecords($user);

        // Seed pipelines
        $this->call(PipelineSeeder::class);

        // Seed wizard drafts
        $this->seedWizardDrafts($user);

        $this->command->newLine();
        $this->command->info('✓ Demo data seeding complete!');
    }

    private function seedContactsRecords(User $user): void
    {
        $module = Module::where('api_name', 'contacts')->first();
        if (!$module) {
            return;
        }

        $this->command->info('Seeding Contacts records...');

        $contacts = [
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'john.smith@example.com',
                'phone' => '+1-555-0101',
                'status' => 'active',
                'company' => 'Acme Corp',
                'job_title' => 'CEO',
                'birthday' => '1980-05-15',
                'notes' => 'Key decision maker. Interested in our enterprise plan.',
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane.doe@example.com',
                'phone' => '+1-555-0102',
                'status' => 'active',
                'company' => 'Tech Innovations',
                'job_title' => 'CTO',
                'birthday' => '1985-08-22',
                'notes' => 'Technical contact. Prefers email communication.',
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Johnson',
                'email' => 'mjohnson@business.com',
                'phone' => '+1-555-0103',
                'status' => 'lead',
                'company' => 'Business Solutions Inc',
                'job_title' => 'Operations Manager',
                'birthday' => '1978-12-10',
                'notes' => 'New lead from conference. Follow up next week.',
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Williams',
                'email' => 'sarah.w@startup.io',
                'phone' => '+1-555-0104',
                'status' => 'active',
                'company' => 'Startup Ventures',
                'job_title' => 'Founder',
                'birthday' => '1990-03-30',
                'notes' => 'Fast-growing startup. High potential for upsell.',
            ],
            [
                'first_name' => 'Robert',
                'last_name' => 'Brown',
                'email' => 'robert.brown@enterprise.com',
                'phone' => '+1-555-0105',
                'status' => 'inactive',
                'company' => 'Enterprise Holdings',
                'job_title' => 'VP of Sales',
                'birthday' => '1975-11-05',
                'notes' => 'Contract ended. Possible renewal in Q3.',
            ],
            [
                'first_name' => 'Emily',
                'last_name' => 'Davis',
                'email' => 'emily.davis@tech.com',
                'phone' => '+1-555-0106',
                'status' => 'active',
                'company' => 'Digital Tech Co',
                'job_title' => 'Product Manager',
                'birthday' => '1988-07-18',
                'notes' => 'Interested in API integration features.',
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Martinez',
                'email' => 'dmartinez@consulting.com',
                'phone' => '+1-555-0107',
                'status' => 'lead',
                'company' => 'Consulting Partners',
                'job_title' => 'Senior Consultant',
                'birthday' => '1982-09-25',
                'notes' => 'Referred by existing client. Schedule demo.',
            ],
            [
                'first_name' => 'Lisa',
                'last_name' => 'Anderson',
                'email' => 'lisa.a@marketing.com',
                'phone' => '+1-555-0108',
                'status' => 'active',
                'company' => 'Marketing Pros',
                'job_title' => 'Marketing Director',
                'birthday' => '1986-04-12',
                'notes' => 'Power user. Provides great feedback.',
            ],
        ];

        foreach ($contacts as $contactData) {
            ModuleRecord::create([
                'module_id' => $module->id,
                'data' => $contactData,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        $this->command->info('  ✓ Created ' . count($contacts) . ' contact records');
    }

    private function seedProductsRecords(User $user): void
    {
        $module = Module::where('api_name', 'products')->first();
        if (!$module) {
            return;
        }

        $this->command->info('Seeding Products records...');

        $products = [
            [
                'product_name' => 'Professional CRM License',
                'sku' => 'CRM-PRO-001',
                'category' => 'electronics',
                'price' => 99.00,
                'cost' => 45.00,
                'stock_quantity' => 1000,
                'description' => 'Full-featured CRM solution for professional teams. Includes advanced reporting and automation.',
            ],
            [
                'product_name' => 'Enterprise CRM License',
                'sku' => 'CRM-ENT-001',
                'category' => 'electronics',
                'price' => 299.00,
                'cost' => 120.00,
                'stock_quantity' => 500,
                'description' => 'Enterprise-grade CRM with unlimited users, custom integrations, and dedicated support.',
            ],
            [
                'product_name' => 'Wireless Mouse',
                'sku' => 'ACC-WM-001',
                'category' => 'electronics',
                'price' => 29.99,
                'cost' => 12.00,
                'stock_quantity' => 250,
                'description' => 'Ergonomic wireless mouse with 6 programmable buttons and long battery life.',
            ],
            [
                'product_name' => 'Mechanical Keyboard',
                'sku' => 'ACC-KB-001',
                'category' => 'electronics',
                'price' => 89.99,
                'cost' => 45.00,
                'stock_quantity' => 150,
                'description' => 'Premium mechanical keyboard with RGB backlighting and custom switches.',
            ],
            [
                'product_name' => 'Office Chair',
                'sku' => 'FUR-CH-001',
                'category' => 'home_garden',
                'price' => 299.99,
                'cost' => 150.00,
                'stock_quantity' => 75,
                'description' => 'Ergonomic office chair with lumbar support and adjustable armrests.',
            ],
            [
                'product_name' => 'Standing Desk',
                'sku' => 'FUR-SD-001',
                'category' => 'home_garden',
                'price' => 599.99,
                'cost' => 300.00,
                'stock_quantity' => 50,
                'description' => 'Electric height-adjustable standing desk with memory presets.',
            ],
        ];

        foreach ($products as $productData) {
            ModuleRecord::create([
                'module_id' => $module->id,
                'data' => $productData,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        $this->command->info('  ✓ Created ' . count($products) . ' product records');
    }

    private function seedDealsRecords(User $user): void
    {
        $module = Module::where('api_name', 'deals')->first();
        if (!$module) {
            return;
        }

        $this->command->info('Seeding Deals records...');

        $deals = [
            [
                'deal_name' => 'Acme Corp - Enterprise Upgrade',
                'stage' => 'negotiation',
                'amount' => 50000.00,
                'close_date' => now()->addDays(15)->format('Y-m-d'),
                'priority' => 'high',
                'description' => 'Upgrading from Professional to Enterprise plan. 200 user licenses.',
            ],
            [
                'deal_name' => 'Tech Innovations - New Customer',
                'stage' => 'proposal',
                'amount' => 25000.00,
                'close_date' => now()->addDays(30)->format('Y-m-d'),
                'priority' => 'high',
                'description' => 'New customer acquisition. 100 user Professional licenses.',
            ],
            [
                'deal_name' => 'Business Solutions - Renewal',
                'stage' => 'qualification',
                'amount' => 15000.00,
                'close_date' => now()->addDays(45)->format('Y-m-d'),
                'priority' => 'medium',
                'description' => 'Annual contract renewal. Existing customer for 2 years.',
            ],
            [
                'deal_name' => 'Startup Ventures - Initial Purchase',
                'stage' => 'prospecting',
                'amount' => 5000.00,
                'close_date' => now()->addDays(60)->format('Y-m-d'),
                'priority' => 'medium',
                'description' => 'Small team starter package. High growth potential.',
            ],
            [
                'deal_name' => 'Enterprise Holdings - Migration',
                'stage' => 'closed_won',
                'amount' => 75000.00,
                'close_date' => now()->subDays(10)->format('Y-m-d'),
                'priority' => 'high',
                'description' => 'Successfully migrated from competitor. 300 user enterprise plan.',
            ],
            [
                'deal_name' => 'Digital Tech Co - Add-on Services',
                'stage' => 'proposal',
                'amount' => 12000.00,
                'close_date' => now()->addDays(20)->format('Y-m-d'),
                'priority' => 'medium',
                'description' => 'Existing customer adding API integration and custom reporting.',
            ],
            [
                'deal_name' => 'Consulting Partners - Partnership',
                'stage' => 'prospecting',
                'amount' => 30000.00,
                'close_date' => now()->addDays(90)->format('Y-m-d'),
                'priority' => 'low',
                'description' => 'Potential reseller partnership. Long sales cycle expected.',
            ],
            [
                'deal_name' => 'Marketing Pros - Contract Extension',
                'stage' => 'closed_won',
                'amount' => 18000.00,
                'close_date' => now()->subDays(5)->format('Y-m-d'),
                'priority' => 'medium',
                'description' => 'Extended contract for 2 additional years. Very satisfied customer.',
            ],
            [
                'deal_name' => 'Small Business Inc - Lost to Competitor',
                'stage' => 'closed_lost',
                'amount' => 8000.00,
                'close_date' => now()->subDays(20)->format('Y-m-d'),
                'priority' => 'low',
                'description' => 'Lost due to pricing. Competitor offered 20% discount.',
            ],
        ];

        foreach ($deals as $dealData) {
            ModuleRecord::create([
                'module_id' => $module->id,
                'data' => $dealData,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        }

        $this->command->info('  ✓ Created ' . count($deals) . ' deal records');
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
                'name' => 'Invoices Module (Nearly Done)',
                'form_data' => [
                    'moduleName' => 'Invoices',
                    'singularName' => 'Invoice',
                    'description' => 'Invoice and billing management',
                    'icon' => 'file-text',
                    'fields' => [
                        ['name' => 'Invoice Number', 'type' => 'text'],
                        ['name' => 'Amount', 'type' => 'currency'],
                        ['name' => 'Due Date', 'type' => 'date'],
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

        $this->command->info('  ✓ Created ' . count($drafts) . ' wizard drafts');
    }
}
