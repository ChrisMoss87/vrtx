<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Performance testing seeder that generates configurable amounts of data.
 * Uses database factories for consistent, realistic data generation.
 *
 * Usage:
 *   php artisan db:seed --class=TestDataSeeder                    # Default: medium dataset
 *   php artisan db:seed --class=TestDataSeeder -- --scale=small   # Small dataset for quick tests
 *   php artisan db:seed --class=TestDataSeeder -- --scale=large   # Large dataset for stress tests
 *   php artisan db:seed --class=TestDataSeeder -- --scale=massive # Massive dataset for load tests
 */
class TestDataSeeder extends Seeder
{
    /**
     * Data scale configurations
     */
    private const SCALES = [
        'small' => [
            'organizations' => 25,
            'contacts' => 50,
            'deals' => 20,
            'activities' => 50,
            'tasks' => 30,
            'cases' => 15,
            'invoices' => 20,
            'quotes' => 10,
            'products' => 15,
            'workflows' => 3,
            'workflow_executions' => 10,
            'blueprints' => 2,
            'reports' => 5,
            'dashboards' => 2,
            'email_accounts' => 2,
            'email_messages' => 30,
            'email_templates' => 5,
            'audit_logs' => 100,
            'imports' => 3,
            'exports' => 3,
            'api_keys' => 2,
            'webhooks' => 3,
        ],
        'medium' => [
            'organizations' => 100,
            'contacts' => 300,
            'deals' => 75,
            'activities' => 200,
            'tasks' => 100,
            'cases' => 50,
            'invoices' => 75,
            'quotes' => 40,
            'products' => 50,
            'workflows' => 10,
            'workflow_executions' => 50,
            'blueprints' => 5,
            'reports' => 15,
            'dashboards' => 5,
            'email_accounts' => 5,
            'email_messages' => 150,
            'email_templates' => 15,
            'audit_logs' => 500,
            'imports' => 10,
            'exports' => 10,
            'api_keys' => 5,
            'webhooks' => 10,
        ],
        'large' => [
            'organizations' => 500,
            'contacts' => 1500,
            'deals' => 400,
            'activities' => 1000,
            'tasks' => 500,
            'cases' => 250,
            'invoices' => 400,
            'quotes' => 200,
            'products' => 150,
            'workflows' => 25,
            'workflow_executions' => 200,
            'blueprints' => 10,
            'reports' => 40,
            'dashboards' => 15,
            'email_accounts' => 10,
            'email_messages' => 500,
            'email_templates' => 30,
            'audit_logs' => 2000,
            'imports' => 25,
            'exports' => 25,
            'api_keys' => 15,
            'webhooks' => 25,
        ],
        'massive' => [
            'organizations' => 2000,
            'contacts' => 10000,
            'deals' => 2000,
            'activities' => 5000,
            'tasks' => 2500,
            'cases' => 1000,
            'invoices' => 2000,
            'quotes' => 1000,
            'products' => 500,
            'workflows' => 50,
            'workflow_executions' => 1000,
            'blueprints' => 20,
            'reports' => 100,
            'dashboards' => 30,
            'email_accounts' => 20,
            'email_messages' => 2000,
            'email_templates' => 50,
            'audit_logs' => 10000,
            'imports' => 50,
            'exports' => 50,
            'api_keys' => 30,
            'webhooks' => 50,
        ],
    ];

    private string $scale = 'medium';
    private array $counts;
    private ?User $user = null;
    private array $moduleCache = [];

    public function run(): void
    {
        $this->scale = $this->command->option('scale') ?? 'medium';

        if (!isset(self::SCALES[$this->scale])) {
            $this->command->error("Invalid scale: {$this->scale}. Use: small, medium, large, or massive");
            return;
        }

        $this->counts = self::SCALES[$this->scale];
        $this->user = User::first();

        if (!$this->user) {
            $this->command->error('No user found. Please create a user first.');
            return;
        }

        $this->command->info("Starting TestDataSeeder with scale: {$this->scale}");
        $this->command->info('');

        $startTime = microtime(true);

        // Cache modules for faster lookup
        $this->cacheModules();

        // Seed data in dependency order
        $this->seedModuleRecords();
        $this->seedWorkflows();
        $this->seedBlueprints();
        $this->seedReportsAndDashboards();
        $this->seedEmailSystem();
        $this->seedAuditLogs();
        $this->seedImportsAndExports();
        $this->seedApiIntegrations();

        $duration = round(microtime(true) - $startTime, 2);
        $this->command->info('');
        $this->command->info("TestDataSeeder completed in {$duration} seconds");
    }

    private function cacheModules(): void
    {
        $modules = DB::table('modules')->get();
        foreach ($modules as $module) {
            $this->moduleCache[$module->api_name] = $module;
        }
    }

    private function getModule(string $apiName): ?Module
    {
        return $this->moduleCache[$apiName] ?? null;
    }

    private function seedModuleRecords(): void
    {
        $this->command->info('Seeding module records...');

        // Organizations
        $orgIds = $this->seedRecordsForModule('organizations', $this->counts['organizations'], function ($faker) {
            return [
                'name' => $faker->company,
                'website' => $faker->url,
                'industry' => $faker->randomElement(['technology', 'healthcare', 'finance', 'retail', 'manufacturing']),
                'phone' => $faker->phoneNumber,
                'email' => $faker->companyEmail,
                'city' => $faker->city,
                'country' => 'united_states',
                'annual_revenue' => $faker->numberBetween(100000, 50000000),
            ];
        });

        // Contacts
        $contactIds = $this->seedRecordsForModule('contacts', $this->counts['contacts'], function ($faker) use ($orgIds) {
            return [
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'organization_id' => !empty($orgIds) ? $faker->randomElement($orgIds) : null,
                'job_title' => $faker->jobTitle,
                'status' => $faker->randomElement(['lead', 'prospect', 'customer', 'inactive']),
            ];
        });

        // Products
        $productIds = $this->seedRecordsForModule('products', $this->counts['products'], function ($faker) {
            $price = $faker->numberBetween(100, 10000);
            return [
                'name' => $faker->words(3, true),
                'sku' => strtoupper($faker->bothify('???-####')),
                'unit_price' => $price,
                'cost' => round($price * 0.6, 2),
                'category' => $faker->randomElement(['software', 'hardware', 'services', 'consulting']),
                'is_active' => $faker->boolean(90),
            ];
        });

        // Deals
        $dealIds = $this->seedRecordsForModule('deals', $this->counts['deals'], function ($faker) use ($orgIds, $contactIds) {
            $module = $this->getModule('deals');
            $pipeline = $module ? DB::table('pipelines')->where('module_id', $module->id)->first() : null;
            $stage = $pipeline ? $pipeline->stages()->inRandomOrder()->first() : null;

            return [
                'name' => $faker->company . ' - ' . $faker->words(2, true),
                'amount' => $faker->numberBetween(5000, 500000),
                'organization_id' => !empty($orgIds) ? $faker->randomElement($orgIds) : null,
                'contact_id' => !empty($contactIds) ? $faker->randomElement($contactIds) : null,
                'stage' => $stage ? (string)$stage->id : null,
                'close_date' => $faker->dateTimeBetween('-1 month', '+3 months')->format('Y-m-d'),
                'source' => $faker->randomElement(['website', 'referral', 'partner', 'outbound']),
            ];
        });

        // Tasks
        $this->seedRecordsForModule('tasks', $this->counts['tasks'], function ($faker) use ($contactIds, $dealIds) {
            return [
                'subject' => $faker->sentence(4),
                'priority' => $faker->randomElement(['low', 'normal', 'high', 'urgent']),
                'status' => $faker->randomElement(['not_started', 'in_progress', 'completed', 'deferred']),
                'due_date' => $faker->dateTimeBetween('-1 week', '+2 weeks')->format('Y-m-d'),
                'related_to_id' => !empty($contactIds) ? $faker->randomElement($contactIds) : null,
            ];
        });

        // Cases
        $this->seedRecordsForModule('cases', $this->counts['cases'], function ($faker) use ($orgIds, $contactIds) {
            return [
                'subject' => $faker->sentence(5),
                'description' => $faker->paragraph,
                'priority' => $faker->randomElement(['low', 'medium', 'high', 'critical']),
                'status' => $faker->randomElement(['new', 'in_progress', 'resolved', 'closed']),
                'contact_id' => !empty($contactIds) ? $faker->randomElement($contactIds) : null,
                'organization_id' => !empty($orgIds) ? $faker->randomElement($orgIds) : null,
            ];
        });

        // Invoices
        $this->seedRecordsForModule('invoices', $this->counts['invoices'], function ($faker) use ($orgIds, $dealIds) {
            $subtotal = $faker->numberBetween(1000, 50000);
            $tax = round($subtotal * 0.1, 2);
            return [
                'invoice_number' => 'INV-' . $faker->unique()->numberBetween(10000, 99999),
                'status' => $faker->randomElement(['draft', 'sent', 'paid', 'overdue']),
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $subtotal + $tax,
                'organization_id' => !empty($orgIds) ? $faker->randomElement($orgIds) : null,
                'invoice_date' => $faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            ];
        });

        // Quotes
        $this->seedRecordsForModule('quotes', $this->counts['quotes'], function ($faker) use ($orgIds, $dealIds) {
            $subtotal = $faker->numberBetween(5000, 100000);
            return [
                'quote_number' => 'QT-' . $faker->unique()->numberBetween(10000, 99999),
                'status' => $faker->randomElement(['draft', 'sent', 'accepted', 'rejected']),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'organization_id' => !empty($orgIds) ? $faker->randomElement($orgIds) : null,
                'deal_id' => !empty($dealIds) ? $faker->randomElement($dealIds) : null,
                'quote_date' => $faker->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            ];
        });

        // Activities (using the Activity model directly)
        $this->seedActivities($contactIds, $dealIds);
    }

    private function seedRecordsForModule(string $moduleApiName, int $count, callable $dataGenerator): array
    {
        $module = $this->getModule($moduleApiName);
        if (!$module) {
            $this->command->warn("  - Module '{$moduleApiName}' not found, skipping");
            return [];
        }

        $faker = \Faker\Factory::create();
        $ids = [];
        $batchSize = 100;
        $batches = ceil($count / $batchSize);

        for ($batch = 0; $batch < $batches; $batch++) {
            $records = [];
            $remaining = min($batchSize, $count - ($batch * $batchSize));

            for ($i = 0; $i < $remaining; $i++) {
                $records[] = [
                    'module_id' => $module->id,
                    'data' => json_encode($dataGenerator($faker)),
                    'created_by' => $this->user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert for performance
            DB::table('module_records')->insert($records);

            // Get the IDs of inserted records
            $insertedIds = DB::table('module_records')
                ->where('module_id', $module->id)
                ->orderBy('id', 'desc')
                ->limit($remaining)
                ->pluck('id')
                ->toArray();

            $ids = array_merge($ids, $insertedIds);
        }

        $this->command->info("  - Created {$count} {$moduleApiName}");
        return $ids;
    }

    private function seedActivities(array $contactIds, array $dealIds): void
    {
        $count = $this->counts['activities'];
        $batchSize = 100;
        $batches = ceil($count / $batchSize);

        for ($batch = 0; $batch < $batches; $batch++) {
            $remaining = min($batchSize, $count - ($batch * $batchSize));

            $activities = Activity::factory()
                ->count($remaining)
                ->state(function () use ($contactIds, $dealIds) {
                    $faker = \Faker\Factory::create();
                    $subjectType = $faker->randomElement(['App\\Models\\ModuleRecord']);
                    $subjectId = !empty($contactIds) ? $faker->randomElement($contactIds) : null;

                    return [
                        'user_id' => $this->user->id,
                        'subject_type' => $subjectType,
                        'subject_id' => $subjectId,
                    ];
                })
                ->create();
        }

        $this->command->info("  - Created {$count} activities");
    }

    private function seedWorkflows(): void
    {
        $this->command->info('Seeding workflows...');

        $dealsModule = $this->getModule('deals');
        $contactsModule = $this->getModule('contacts');
        $modules = array_filter([$dealsModule, $contactsModule]);

        if (empty($modules)) {
            $this->command->warn('  - No modules found for workflows, skipping');
            return;
        }

        $count = $this->counts['workflows'];

        for ($i = 0; $i < $count; $i++) {
            $module = $modules[array_rand($modules)];

            $workflow = Workflow::factory()
                ->for($this->user)
                ->create([
                    'module_id' => $module->id,
                ]);

            // Add 2-5 steps per workflow
            $stepCount = rand(2, 5);
            for ($s = 0; $s < $stepCount; $s++) {
                WorkflowStep::factory()
                    ->for($workflow)
                    ->create(['order' => $s + 1]);
            }

            // Add some executions
            $execCount = min(10, $this->counts['workflow_executions'] / $count);
            for ($e = 0; $e < $execCount; $e++) {
                WorkflowExecution::factory()
                    ->for($workflow)
                    ->create(['triggered_by' => $this->user->id]);
            }
        }

        $this->command->info("  - Created {$count} workflows with steps and executions");
    }

    private function seedBlueprints(): void
    {
        $this->command->info('Seeding blueprints...');

        $dealsModule = $this->getModule('deals');
        $casesModule = $this->getModule('cases');
        $modules = array_filter([$dealsModule, $casesModule]);

        if (empty($modules)) {
            $this->command->warn('  - No modules found for blueprints, skipping');
            return;
        }

        $count = $this->counts['blueprints'];

        for ($i = 0; $i < $count; $i++) {
            $module = $modules[array_rand($modules)];

            $blueprint = Blueprint::factory()
                ->for($this->user, 'creator')
                ->create([
                    'module_id' => $module->id,
                ]);

            // Create states
            $states = [];
            $stateNames = ['Draft', 'Pending Review', 'Approved', 'Rejected'];
            foreach ($stateNames as $index => $name) {
                $state = BlueprintState::factory()
                    ->for($blueprint)
                    ->create([
                        'name' => $name,
                        'is_initial' => $index === 0,
                        'is_final' => in_array($name, ['Approved', 'Rejected']),
                        'order' => $index,
                    ]);
                $states[] = $state;
            }

            // Create transitions between states
            for ($t = 0; $t < count($states) - 1; $t++) {
                BlueprintTransition::factory()
                    ->for($blueprint)
                    ->from($states[$t])
                    ->to($states[$t + 1])
                    ->create();
            }
        }

        $this->command->info("  - Created {$count} blueprints with states and transitions");
    }

    private function seedReportsAndDashboards(): void
    {
        $this->command->info('Seeding reports and dashboards...');

        $modules = array_values(array_filter([
            $this->getModule('deals'),
            $this->getModule('contacts'),
            $this->getModule('organizations'),
            $this->getModule('cases'),
        ]));

        if (empty($modules)) {
            $this->command->warn('  - No modules found for reports, skipping');
            return;
        }

        // Reports
        $reportCount = $this->counts['reports'];
        for ($i = 0; $i < $reportCount; $i++) {
            $module = $modules[array_rand($modules)];
            Report::factory()
                ->for($this->user)
                ->create(['module_id' => $module->id]);
        }
        $this->command->info("  - Created {$reportCount} reports");

        // Dashboards
        $dashboardCount = $this->counts['dashboards'];
        for ($i = 0; $i < $dashboardCount; $i++) {
            $dashboard = Dashboard::factory()
                ->for($this->user)
                ->create();

            // Add 4-8 widgets per dashboard
            $widgetCount = rand(4, 8);
            for ($w = 0; $w < $widgetCount; $w++) {
                $module = $modules[array_rand($modules)];
                DashboardWidget::factory()
                    ->for($dashboard)
                    ->create([
                        'config' => [
                            'module_id' => $module->id,
                            'title' => 'Widget ' . ($w + 1),
                        ],
                    ]);
            }
        }
        $this->command->info("  - Created {$dashboardCount} dashboards with widgets");
    }

    private function seedEmailSystem(): void
    {
        $this->command->info('Seeding email system...');

        // Email accounts
        $accountCount = $this->counts['email_accounts'];
        $accounts = [];
        for ($i = 0; $i < $accountCount; $i++) {
            $accounts[] = EmailAccount::factory()
                ->for($this->user)
                ->create();
        }
        $this->command->info("  - Created {$accountCount} email accounts");

        // Email messages
        if (!empty($accounts)) {
            $messageCount = $this->counts['email_messages'];
            $batchSize = 100;

            for ($batch = 0; $batch * $batchSize < $messageCount; $batch++) {
                $remaining = min($batchSize, $messageCount - ($batch * $batchSize));
                for ($i = 0; $i < $remaining; $i++) {
                    EmailMessage::factory()
                        ->for($accounts[array_rand($accounts)])
                        ->create();
                }
            }
            $this->command->info("  - Created {$messageCount} email messages");
        }

        // Email templates
        $templateCount = $this->counts['email_templates'];
        for ($i = 0; $i < $templateCount; $i++) {
            EmailTemplate::factory()
                ->for($this->user, 'creator')
                ->create();
        }
        $this->command->info("  - Created {$templateCount} email templates");
    }

    private function seedAuditLogs(): void
    {
        $this->command->info('Seeding audit logs...');

        $count = $this->counts['audit_logs'];
        $batchSize = 200;

        // Get some module records to reference
        $records = ModuleRecord::limit(100)->pluck('id')->toArray();

        for ($batch = 0; $batch * $batchSize < $count; $batch++) {
            $remaining = min($batchSize, $count - ($batch * $batchSize));

            for ($i = 0; $i < $remaining; $i++) {
                AuditLog::factory()
                    ->for($this->user)
                    ->state(function () use ($records) {
                        $faker = \Faker\Factory::create();
                        return [
                            'auditable_type' => 'App\\Models\\ModuleRecord',
                            'auditable_id' => !empty($records) ? $faker->randomElement($records) : 1,
                        ];
                    })
                    ->create();
            }
        }

        $this->command->info("  - Created {$count} audit logs");
    }

    private function seedImportsAndExports(): void
    {
        $this->command->info('Seeding imports and exports...');

        $modules = array_values(array_filter([
            $this->getModule('contacts'),
            $this->getModule('organizations'),
            $this->getModule('deals'),
        ]));

        if (empty($modules)) {
            $this->command->warn('  - No modules found for imports/exports, skipping');
            return;
        }

        // Imports
        $importCount = $this->counts['imports'];
        for ($i = 0; $i < $importCount; $i++) {
            $module = $modules[array_rand($modules)];
            Import::factory()
                ->for($this->user)
                ->for($module)
                ->create();
        }
        $this->command->info("  - Created {$importCount} imports");

        // Exports
        $exportCount = $this->counts['exports'];
        for ($i = 0; $i < $exportCount; $i++) {
            $module = $modules[array_rand($modules)];
            Export::factory()
                ->for($this->user)
                ->for($module)
                ->create();
        }
        $this->command->info("  - Created {$exportCount} exports");
    }

    private function seedApiIntegrations(): void
    {
        $this->command->info('Seeding API integrations...');

        $modules = array_values(array_filter([
            $this->getModule('contacts'),
            $this->getModule('deals'),
        ]));

        // API Keys
        $apiKeyCount = $this->counts['api_keys'];
        for ($i = 0; $i < $apiKeyCount; $i++) {
            ApiKey::factory()
                ->for($this->user)
                ->create();
        }
        $this->command->info("  - Created {$apiKeyCount} API keys");

        // Webhooks
        if (!empty($modules)) {
            $webhookCount = $this->counts['webhooks'];
            for ($i = 0; $i < $webhookCount; $i++) {
                $module = $modules[array_rand($modules)];
                Webhook::factory()
                    ->for($this->user)
                    ->for($module)
                    ->create();
            }
            $this->command->info("  - Created {$webhookCount} webhooks");
        }
    }
}
