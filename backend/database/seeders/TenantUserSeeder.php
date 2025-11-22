<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds test users for each tenant database.
 *
 * This seeder should be run WITHIN tenant context using:
 * php artisan tenants:seed --class=TenantUserSeeder
 *
 * Or it will be automatically called after tenant migrations.
 */
class TenantUserSeeder extends Seeder
{
    /**
     * Consistent test users for each tenant.
     * Password for all users: password123
     */
    private const USERS = [
        'acme' => [
            [
                'name' => 'John Acme',
                'email' => 'john@acme.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Test Acme User',
                'email' => 'testuser@acme.com',
                'password' => 'password123',
            ],
        ],
        'techco' => [
            [
                'name' => 'Bob TechCo',
                'email' => 'bob@techco.com',
                'password' => 'password123',
            ],
            [
                'name' => 'Test TechCo User',
                'email' => 'testuser@techco.com',
                'password' => 'password123',
            ],
        ],
        'startup' => [
            [
                'name' => 'Alice Startup',
                'email' => 'alice@startup.com',
                'password' => 'password123',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = tenant('id');

        if (!$tenantId) {
            $this->command->error('This seeder must be run in tenant context!');
            $this->command->line('Use: php artisan tenants:seed --class=TenantUserSeeder');
            return;
        }

        if (!isset(self::USERS[$tenantId])) {
            $this->command->warn("No predefined users for tenant: {$tenantId}");
            return;
        }

        $users = self::USERS[$tenantId];

        foreach ($users as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'email_verified_at' => now(),
            ]);

            $this->command->info("✓ Created user: {$user->name} ({$user->email})");
        }

        $this->command->newLine();
        $this->command->info("Seeded " . count($users) . " user(s) for tenant: {$tenantId}");
    }
}
