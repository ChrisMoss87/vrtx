<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates seeding of all default data for a new tenant.
 * This should be called after a tenant is created.
 */
class TenantDefaultDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Configuration options can be set via the $this->command property:
     * - --preset=starter|sales|support|full (default: full)
     * - --with-sample-data (include sample records)
     */
    public function run(): void
    {
        $preset = $this->getPreset();

        $this->command->info("Seeding tenant default data (preset: {$preset})...");
        $this->command->newLine();

        // Initialize subscription/license for the tenant
        $this->initializeSubscription();

        // Always seed modules first
        $this->call(DefaultModulesSeeder::class);
        $this->command->newLine();

        // Seed pipelines based on preset
        if ($this->shouldSeedPipelines($preset)) {
            $this->call(DefaultPipelinesSeeder::class);
            $this->command->newLine();
        }

        // Seed views for all presets
        $this->call(DefaultViewsSeeder::class);
        $this->command->newLine();

        // Seed reports based on preset
        if ($this->shouldSeedReports($preset)) {
            $this->call(DefaultReportsSeeder::class);
            $this->command->newLine();
        }

        // Seed dashboards based on preset
        if ($this->shouldSeedDashboards($preset)) {
            $this->call(DefaultDashboardsSeeder::class);
            $this->command->newLine();
        }

        // Optionally seed sample data
        if ($this->shouldSeedSampleData()) {
            $this->call(SampleDataSeeder::class);
            $this->command->newLine();
        }

        $this->command->info('Tenant default data seeded successfully!');
    }

    private function getPreset(): string
    {
        // Default to 'full' preset
        return 'full';
    }

    private function shouldSeedPipelines(string $preset): bool
    {
        return in_array($preset, ['sales', 'support', 'full']);
    }

    private function shouldSeedReports(string $preset): bool
    {
        return in_array($preset, ['sales', 'support', 'full']);
    }

    private function shouldSeedDashboards(string $preset): bool
    {
        return in_array($preset, ['sales', 'support', 'full']);
    }

    private function shouldSeedSampleData(): bool
    {
        // Can be controlled via environment or parameter
        return config('app.seed_sample_data', false);
    }

    /**
     * Initialize the tenant's subscription with a free plan by default.
     * This can be overridden for tenants that sign up with a paid plan.
     */
    private function initializeSubscription(): void
    {
        // Check if a subscription already exists
        if (TenantSubscription::exists()) {
            $this->command->info('✓ Subscription already exists');
            return;
        }

        // Create a default free subscription
        DB::table('tenant_subscriptions')->insertGetId([
            'plan' => TenantSubscription::PLAN_FREE,
            'status' => TenantSubscription::STATUS_ACTIVE,
            'billing_cycle' => TenantSubscription::CYCLE_MONTHLY,
            'user_count' => 1,
            'price_per_user' => 0,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->command->info('✓ Initialized free subscription');
    }
}
