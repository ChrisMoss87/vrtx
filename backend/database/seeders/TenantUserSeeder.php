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
        'techco' => [
            [
                'name' => 'Bob TechCo',
                'email' => 'bob@techco.com',
                'password' => 'password123',
                'role' => 'admin',
            ],
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah@techco.com',
                'password' => 'password123',
                'role' => 'manager',
            ],
            [
                'name' => 'Mike Davis',
                'email' => 'mike@techco.com',
                'password' => 'password123',
                'role' => 'sales_rep',
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
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'email_verified_at' => now(),
                ]
            );

            // Assign role if roles exist
            if (isset($userData['role']) && class_exists(\Spatie\Permission\Models\Role::class)) {
                try {
                    $user->syncRoles([$userData['role']]);
                    $this->command->info("✓ Created/updated user: {$user->name} ({$user->email}) with role: {$userData['role']}");
                } catch (\Exception $e) {
                    $this->command->info("✓ Created/updated user: {$user->name} ({$user->email})");
                }
            } else {
                $this->command->info("✓ Created/updated user: {$user->name} ({$user->email})");
            }
        }

        $this->command->newLine();
        $this->command->info("Seeded " . count($users) . " user(s) for tenant: {$tenantId}");
    }
}
