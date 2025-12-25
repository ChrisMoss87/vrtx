<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Stancl\Tenancy\Database\Models\Domain;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    /**
     * Test tenants configuration.
     */
    private const TENANTS = [
        'techco' => [
            'name' => 'TechCo Solutions',
            'domain' => 'techco.vrtx.local',
            'plan' => 'enterprise',
            'users_limit' => 200,
            'storage_limit_mb' => 20000,
        ],
        'acme' => [
            'name' => 'Acme Corporation',
            'domain' => 'acme.vrtx.local',
            'plan' => 'professional',
            'users_limit' => 50,
            'storage_limit_mb' => 5000,
        ],
        'startup' => [
            'name' => 'Startup Inc',
            'domain' => 'startup.vrtx.local',
            'plan' => 'starter',
            'users_limit' => 10,
            'storage_limit_mb' => 1000,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::TENANTS as $id => $config) {
            $tenant = DB::table('tenants')->insertGetId([
                'id' => $id,
                'data' => [
                    'name' => $config['name'],
                    'plan' => $config['plan'],
                    'status' => 'active',
                    'users_limit' => $config['users_limit'],
                    'storage_limit_mb' => $config['storage_limit_mb'],
                ]
            ]);

            Domain::create([
                'domain' => $config['domain'],
                'tenant_id' => $id,
            ]);

            $this->command->info("✓ Created tenant: {$id} ({$config['domain']})");
        }

        $this->command->newLine();
        $this->command->info('========================================');
        $this->command->info('Tenant Seeding Complete!');
        $this->command->info('========================================');
        $this->command->newLine();
        $this->command->warn('Next: Add to /etc/hosts:');
        foreach (self::TENANTS as $id => $config) {
            $this->command->line("   127.0.0.1 {$config['domain']}");
        }
    }
}
