<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\User\Repositories\UserRepositoryInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds test users for each tenant database.
 *
 * This seeder should be run WITHIN tenant context using:
 * php artisan tenants:seed --class=TenantUserSeeder
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
        'acme' => [
            [
                'name' => 'Admin Acme',
                'email' => 'admin@acme.com',
                'password' => 'password123',
                'role' => 'admin',
            ],
            [
                'name' => 'John Smith',
                'email' => 'john@acme.com',
                'password' => 'password123',
                'role' => 'manager',
            ],
            [
                'name' => 'Jane Doe',
                'email' => 'jane@acme.com',
                'password' => 'password123',
                'role' => 'sales_rep',
            ],
        ],
        'startup' => [
            [
                'name' => 'Alice Startup',
                'email' => 'alice@startup.com',
                'password' => 'password123',
                'role' => 'admin',
            ],
            [
                'name' => 'Charlie Brown',
                'email' => 'charlie@startup.com',
                'password' => 'password123',
                'role' => 'sales_rep',
            ],
        ],
    ];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

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

        // Seed roles and permissions first
        $this->seedRolesAndPermissions();

        if (!isset(self::USERS[$tenantId])) {
            $this->command->warn("No predefined users for tenant: {$tenantId}");
            // Create a default admin user for unknown tenants
            $this->createDefaultAdmin($tenantId);
            return;
        }

        $users = self::USERS[$tenantId];

        foreach ($users as $userData) {
            $user = $this->createOrUpdateUser($userData);

            // Assign role using Spatie (third-party package)
            if (isset($userData['role'])) {
                $this->assignRoleToUser($user['id'], $userData['role']);
                $this->command->info("Created user: {$user['name']} ({$user['email']}) with role: {$userData['role']}");
            } else {
                $this->command->info("Created user: {$user['name']} ({$user['email']})");
            }
        }

        $this->command->newLine();
        $this->command->info("Seeded " . count($users) . " user(s) for tenant: {$tenantId}");
    }

    /**
     * Create or update a user using the repository.
     */
    private function createOrUpdateUser(array $userData): array
    {
        $existingUser = $this->userRepository->findByEmail($userData['email']);

        if ($existingUser !== null) {
            return $this->userRepository->update($existingUser['id'], [
                'name' => $userData['name'],
                'password' => Hash::make($userData['password']),
                'email_verified_at' => now()->format('Y-m-d H:i:s'),
            ]);
        }

        return $this->userRepository->create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'email_verified_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Assign a role to a user using Spatie permissions.
     * Note: We use Spatie's models here as it's a third-party package.
     */
    private function assignRoleToUser(int $userId, string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            return;
        }

        // Use model_has_roles table directly to avoid Eloquent model dependency
        DB::table('model_has_roles')->updateOrInsert(
            [
                'role_id' => $role->id,
                'model_type' => 'App\\Infrastructure\\Persistence\\Eloquent\\Models\\User',
                'model_id' => $userId,
            ],
            []
        );
    }

    /**
     * Seed roles and permissions for the tenant.
     */
    private function seedRolesAndPermissions(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Use permissions from RolesAndPermissionsSeeder
        $permissions = RolesAndPermissionsSeeder::PERMISSIONS;

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->command->info('Created ' . count($permissions) . ' permissions');

        // Create roles with their permissions
        foreach (RolesAndPermissionsSeeder::ROLES as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($rolePermissions === '*') {
                $role->givePermissionTo(Permission::all());
            } else {
                $role->syncPermissions($rolePermissions);
            }
        }

        $this->command->info('Created roles: ' . implode(', ', array_keys(RolesAndPermissionsSeeder::ROLES)));
    }

    /**
     * Create a default admin for unknown tenants.
     */
    private function createDefaultAdmin(string $tenantId): void
    {
        $user = $this->createOrUpdateUser([
            'name' => 'Admin',
            'email' => "admin@{$tenantId}.com",
            'password' => 'password123',
        ]);

        $this->assignRoleToUser($user['id'], 'admin');

        $this->command->info("Created default admin: admin@{$tenantId}.com / password123");
    }
}
