<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Domain\User\Entities\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();

        // Create admin role with all RBAC permissions
        $this->adminRole = DB::table('roles')->insertGetId(['name' => 'super_admin', 'guard_name' => 'web']);
        $permissions = [
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'permissions.view',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
        ];
        foreach ($permissions as $permName) {
            $perm = DB::table('permissions')->insertGetId(['name' => $permName, 'guard_name' => 'web']);
            $this->adminRole->givePermissionTo($perm);
        }
        $this->adminUser->assignRole($this->adminRole);

        Sanctum::actingAs($this->adminUser);
    }

    // ==========================================
    // Role Tests
    // ==========================================

    public function test_can_list_roles(): void
    {
        DB::table('roles')->insertGetId(['name' => 'manager', 'guard_name' => 'web']);
        DB::table('roles')->insertGetId(['name' => 'sales_rep', 'guard_name' => 'web']);

        $response = $this->getJson('/api/v1/roles');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'roles' => [
                    '*' => ['id', 'name'],
                ],
            ]);
    }

    public function test_can_create_role(): void
    {
        $response = $this->postJson('/api/v1/roles', [
            'name' => 'new_role',
            'description' => 'A new custom role',
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'new_role',
        ]);
    }

    public function test_cannot_create_duplicate_role(): void
    {
        DB::table('roles')->insertGetId(['name' => 'existing_role', 'guard_name' => 'web']);

        $response = $this->postJson('/api/v1/roles', [
            'name' => 'existing_role',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_show_role(): void
    {
        $role = DB::table('roles')->insertGetId(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->getJson("/api/v1/roles/{$role->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'role' => [
                    'id' => $role->id,
                    'name' => 'test_role',
                ],
            ]);
    }

    public function test_can_update_role(): void
    {
        $role = DB::table('roles')->insertGetId(['name' => 'old_name', 'guard_name' => 'web']);

        $response = $this->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'updated_name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'updated_name',
        ]);
    }

    public function test_can_delete_role(): void
    {
        $role = DB::table('roles')->insertGetId(['name' => 'deletable_role', 'guard_name' => 'web']);

        $response = $this->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_role_with_users(): void
    {
        $role = DB::table('roles')->insertGetId(['name' => 'role_with_users', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->deleteJson("/api/v1/roles/{$role->id}");

        // Should either fail or require force delete
        $response->assertStatus(422);
    }

    // ==========================================
    // Role Permissions Tests
    // ==========================================

    public function test_can_assign_permissions_to_role(): void
    {
        $role = DB::table('roles')->insertGetId(['name' => 'test_role', 'guard_name' => 'web']);
        $permission1 = DB::table('permissions')->insertGetId(['name' => 'test.view', 'guard_name' => 'web']);
        $permission2 = DB::table('permissions')->insertGetId(['name' => 'test.create', 'guard_name' => 'web']);

        $response = $this->postJson("/api/v1/roles/{$role->id}/permissions", [
            'permissions' => [$permission1->name, $permission2->name],
        ]);

        $response->assertOk();
        $this->assertTrue($role->hasPermissionTo('test.view'));
        $this->assertTrue($role->hasPermissionTo('test.create'));
    }

    public function test_can_sync_role_permissions(): void
    {
        $role = DB::table('roles')->insertGetId(['name' => 'test_role', 'guard_name' => 'web']);
        $permission1 = DB::table('permissions')->insertGetId(['name' => 'test.view', 'guard_name' => 'web']);
        $permission2 = DB::table('permissions')->insertGetId(['name' => 'test.create', 'guard_name' => 'web']);
        $permission3 = DB::table('permissions')->insertGetId(['name' => 'test.delete', 'guard_name' => 'web']);

        $role->givePermissionTo($permission1);

        $response = $this->putJson("/api/v1/roles/{$role->id}/permissions", [
            'permissions' => [$permission2->name, $permission3->name],
        ]);

        $response->assertOk();
        $this->assertFalse($role->fresh()->hasPermissionTo('test.view'));
        $this->assertTrue($role->fresh()->hasPermissionTo('test.create'));
        $this->assertTrue($role->fresh()->hasPermissionTo('test.delete'));
    }

    public function test_can_remove_permission_from_role(): void
    {
        $role = DB::table('roles')->insertGetId(['name' => 'test_role', 'guard_name' => 'web']);
        $permission = DB::table('permissions')->insertGetId(['name' => 'test.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $response = $this->deleteJson("/api/v1/roles/{$role->id}/permissions/{$permission->name}");

        $response->assertOk();
        $this->assertFalse($role->fresh()->hasPermissionTo('test.view'));
    }

    // ==========================================
    // Permission Tests
    // ==========================================

    public function test_can_list_permissions(): void
    {
        $response = $this->getJson('/api/v1/permissions');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'permissions',
            ]);
    }

    public function test_can_get_grouped_permissions(): void
    {
        DB::table('permissions')->insertGetId(['name' => 'contacts.view', 'guard_name' => 'web']);
        DB::table('permissions')->insertGetId(['name' => 'contacts.create', 'guard_name' => 'web']);
        DB::table('permissions')->insertGetId(['name' => 'deals.view', 'guard_name' => 'web']);

        $response = $this->getJson('/api/v1/permissions?grouped=true');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'permissions' => [
                    'contacts',
                    'deals',
                ],
            ]);
    }

    // ==========================================
    // User Role Tests
    // ==========================================

    public function test_can_list_users(): void
    {
        User::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'users' => [
                    'data',
                    'current_page',
                    'total',
                ],
            ]);
    }

    public function test_can_assign_role_to_user(): void
    {
        $user = User::factory()->create();
        $role = DB::table('roles')->insertGetId(['name' => 'test_role', 'guard_name' => 'web']);

        $response = $this->postJson("/api/v1/users/{$user->id}/roles", [
            'roles' => [$role->name],
        ]);

        $response->assertOk();
        $this->assertTrue($user->fresh()->hasRole('test_role'));
    }

    public function test_can_remove_role_from_user(): void
    {
        $user = User::factory()->create();
        $role = DB::table('roles')->insertGetId(['name' => 'test_role', 'guard_name' => 'web']);
        $user->assignRole($role);

        $response = $this->deleteJson("/api/v1/users/{$user->id}/roles/{$role->name}");

        $response->assertOk();
        $this->assertFalse($user->fresh()->hasRole('test_role'));
    }

    public function test_can_sync_user_roles(): void
    {
        $user = User::factory()->create();
        $role1 = DB::table('roles')->insertGetId(['name' => 'role1', 'guard_name' => 'web']);
        $role2 = DB::table('roles')->insertGetId(['name' => 'role2', 'guard_name' => 'web']);
        $role3 = DB::table('roles')->insertGetId(['name' => 'role3', 'guard_name' => 'web']);

        $user->assignRole($role1);

        $response = $this->putJson("/api/v1/users/{$user->id}/roles", [
            'roles' => [$role2->name, $role3->name],
        ]);

        $response->assertOk();
        $this->assertFalse($user->fresh()->hasRole('role1'));
        $this->assertTrue($user->fresh()->hasRole('role2'));
        $this->assertTrue($user->fresh()->hasRole('role3'));
    }

    public function test_can_get_user_permissions(): void
    {
        $user = User::factory()->create();
        $role = DB::table('roles')->insertGetId(['name' => 'test_role', 'guard_name' => 'web']);
        $permission = DB::table('permissions')->insertGetId(['name' => 'test.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $response = $this->getJson("/api/v1/users/{$user->id}/permissions");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'permissions',
            ]);
    }

    // ==========================================
    // Direct User Permission Tests
    // ==========================================

    public function test_can_assign_direct_permission_to_user(): void
    {
        $user = User::factory()->create();
        $permission = DB::table('permissions')->insertGetId(['name' => 'special.permission', 'guard_name' => 'web']);

        $response = $this->postJson("/api/v1/users/{$user->id}/permissions", [
            'permissions' => [$permission->name],
        ]);

        $response->assertOk();
        $this->assertTrue($user->fresh()->hasDirectPermission('special.permission'));
    }

    // ==========================================
    // Authorization Tests
    // ==========================================

    public function test_user_without_permission_cannot_manage_roles(): void
    {
        $regularUser = User::factory()->create();
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/v1/roles');

        $response->assertForbidden();
    }

    public function test_user_without_permission_cannot_manage_users(): void
    {
        $regularUser = User::factory()->create();
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/v1/users');

        $response->assertForbidden();
    }

    // ==========================================
    // Clone Role Tests
    // ==========================================

    public function test_can_clone_role(): void
    {
        $role = DB::table('roles')->insertGetId(['name' => 'original_role', 'guard_name' => 'web']);
        $permission = DB::table('permissions')->insertGetId(['name' => 'test.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $response = $this->postJson("/api/v1/roles/{$role->id}/clone", [
            'name' => 'cloned_role',
        ]);

        $response->assertOk();

        $clonedRole = DB::table('roles')->where('name', 'cloned_role')->first();
        $this->assertNotNull($clonedRole);
        $this->assertTrue($clonedRole->hasPermissionTo('test.view'));
    }
}
