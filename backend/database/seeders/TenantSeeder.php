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
        // Only create TechCo tenant for now
        $techco = Tenant::create([
            'id' => 'techco',
            'data' => [
                'name' => 'TechCo Solutions',
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

        $this->command->newLine();
        $this->command->info('========================================');
        $this->command->info('Tenant Seeding Complete!');
        $this->command->info('========================================');
        $this->command->newLine();
        $this->command->warn('Next steps:');
        $this->command->line('1. Add to /etc/hosts:');
        $this->command->line('   127.0.0.1 techco.vrtx.local');
        $this->command->newLine();
        $this->command->line('2. Run tenant migrations:');
        $this->command->line('   php artisan tenants:migrate');
        $this->command->newLine();
        $this->command->line('3. Seed tenant data:');
        $this->command->line('   php artisan tenants:seed');
        $this->command->newLine();
        $this->command->line('4. Access tenant:');
        $this->command->line('   http://techco.vrtx.local:5173');
    }
}
