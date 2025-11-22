<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tenant 1: Subdomain example (acme.vrtx.local)
        $acme = Tenant::create([
            'id' => 'acme',
            'data' => [
                'name' => 'Acme Corporation',
                'plan' => 'professional',
                'status' => 'active',
                'users_limit' => 50,
                'storage_limit_mb' => 5000,
            ]
        ]);

        Domain::create([
            'domain' => 'acme.vrtx.local',
            'tenant_id' => 'acme',
        ]);

        $this->command->info('✓ Created tenant: acme (acme.vrtx.local)');

        // Tenant 2: Subdomain example (techco.vrtx.local)
        $techco = Tenant::create([
            'id' => 'techco',
            'data' => [
                'name' => 'TechCo Inc',
                'plan' => 'enterprise',
                'status' => 'active',
                'users_limit' => 200,
                'storage_limit_mb' => 20000,
            ]
        ]);

        Domain::create([
            'domain' => 'techco.vrtx.local',
            'tenant_id' => 'techco',
        ]);

        $this->command->info('✓ Created tenant: techco (techco.vrtx.local)');

        // Tenant 3: Custom domain example
        $startup = Tenant::create([
            'id' => 'startup',
            'data' => [
                'name' => 'Startup Inc',
                'plan' => 'starter',
                'status' => 'trial',
                'users_limit' => 10,
                'storage_limit_mb' => 1000,
                'trial_ends_at' => now()->addDays(14)->toDateTimeString(),
            ]
        ]);

        // Subdomain
        Domain::create([
            'domain' => 'startup.vrtx.local',
            'tenant_id' => 'startup',
        ]);

        // Custom domain example (would need DNS setup in production)
        Domain::create([
            'domain' => 'crm.startup.com',
            'tenant_id' => 'startup',
        ]);

        $this->command->info('✓ Created tenant: startup (startup.vrtx.local + crm.startup.com)');

        $this->command->newLine();
        $this->command->info('========================================');
        $this->command->info('Tenant Seeding Complete!');
        $this->command->info('========================================');
        $this->command->newLine();
        $this->command->warn('Next steps:');
        $this->command->line('1. Add to /etc/hosts:');
        $this->command->line('   127.0.0.1 acme.vrtx.local');
        $this->command->line('   127.0.0.1 techco.vrtx.local');
        $this->command->line('   127.0.0.1 startup.vrtx.local');
        $this->command->line('   127.0.0.1 crm.startup.com');
        $this->command->newLine();
        $this->command->line('2. Run tenant migrations:');
        $this->command->line('   php artisan tenants:migrate');
        $this->command->newLine();
        $this->command->line('3. Access tenants:');
        $this->command->line('   http://acme.vrtx.local:5173');
        $this->command->line('   http://techco.vrtx.local:5173');
        $this->command->line('   http://startup.vrtx.local:5173');
        $this->command->line('   http://crm.startup.com:5173');
    }
}
