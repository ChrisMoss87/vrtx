<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;
use Illuminate\Support\Facades\DB;

    protected User $user;
    protected Module $module;
    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = /* User factory - use DB::table('users') */->create();
        $this->module = /* Module factory - use DB::table('modules') */->create([
            'name' => 'Deals',
            'singular_name' => 'Deal',
            'api_name' => 'deals',
        ]);

        // Create role with dashboard permissions
        $this->role = DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web']);
        $permissions = [
            'dashboards.view',
            'dashboards.create',
            'dashboards.edit',
            'dashboards.delete',
        ];
        foreach ($permissions as $permName) {
            $perm = DB::table('permissions')->insertGetId(['name' => $permName, 'guard_name' => 'web']);
            $this->role->givePermissionTo($perm);
        }
        $this->user->assignRole($this->role);

        Sanctum::actingAs($this->user);
    }

    // ==========================================
    // Index / List Tests
    // ==========================================

    public function test_can_list_dashboards(): void
    {
        /* Dashboard factory - use DB::table('dashboards') */->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/dashboards');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'dashboards' => [
                    '*' => ['id', 'name', 'is_default', 'is_public'],
                ],
            ]);
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function test_can_create_dashboard(): void
    {
        $response = $this->postJson('/api/v1/dashboards', [
            'name' => 'Sales Dashboard',
            'description' => 'Overview of sales metrics',
            'is_default' => true,
            'layout' => [
                'columns' => 12,
                'rows' => 'auto',
            ],
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('dashboard.name', 'Sales Dashboard');

        $this->assertDatabaseHas('dashboards', [
            'name' => 'Sales Dashboard',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_cannot_create_dashboard_without_name(): void
    {
        $response = $this->postJson('/api/v1/dashboards', [
            'description' => 'No name',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ==========================================
    // Show Tests
    // ==========================================

    public function test_can_show_dashboard(): void
    {
        $dashboard = /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/dashboards/{$dashboard->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'dashboard' => [
                    'id' => $dashboard->id,
                    'name' => $dashboard->name,
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_dashboard(): void
    {
        $response = $this->getJson('/api/v1/dashboards/99999');

        $response->assertNotFound();
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function test_can_update_dashboard(): void
    {
        $dashboard = /* Dashboard factory - use DB::table('dashboards') */->create([
            'name' => 'Original Name',
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/dashboards/{$dashboard->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('dashboards', [
            'id' => $dashboard->id,
            'name' => 'Updated Name',
        ]);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function test_can_delete_dashboard(): void
    {
        $dashboard = /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/dashboards/{$dashboard->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('dashboards', ['id' => $dashboard->id]);
    }

    // ==========================================
    // Default Dashboard Tests
    // ==========================================

    public function test_can_set_dashboard_as_default(): void
    {
        $dashboard1 = /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);
        $dashboard2 = /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
            'is_default' => false,
        ]);

        $response = $this->postJson("/api/v1/dashboards/{$dashboard2->id}/set-default");

        $response->assertOk();
        $this->assertTrue($dashboard2->fresh()->is_default);
        $this->assertFalse($dashboard1->fresh()->is_default);
    }

    public function test_can_get_default_dashboard(): void
    {
        /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
            'is_default' => false,
        ]);
        $defaultDashboard = /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
            'is_default' => true,
        ]);

        $response = $this->getJson('/api/v1/dashboards/default');

        $response->assertOk()
            ->assertJsonPath('dashboard.id', $defaultDashboard->id);
    }

    // ==========================================
    // Widget Tests
    // ==========================================

    public function test_can_add_widget_to_dashboard(): void
    {
        $dashboard = /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/dashboards/{$dashboard->id}/widgets", [
            'type' => 'kpi',
            'title' => 'Total Revenue',
            'config' => [
                'module_id' => $this->module->id,
                'field' => 'amount',
                'aggregation' => 'sum',
            ],
            'position' => ['x' => 0, 'y' => 0],
            'size' => ['w' => 3, 'h' => 2],
        ]);

        $response->assertCreated()
            ->assertJsonPath('widget.title', 'Total Revenue');

        $this->assertDatabaseHas('dashboard_widgets', [
            'dashboard_id' => $dashboard->id,
            'title' => 'Total Revenue',
            'type' => 'kpi',
        ]);
    }

    public function test_can_update_widget(): void
    {
        $dashboard = /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
        ]);
        $widget = /* DashboardWidget factory - use DB::table('dashboard_widgets') */->create([
            'dashboard_id' => $dashboard->id,
            'title' => 'Original Title',
        ]);

        $response = $this->putJson("/api/v1/dashboards/{$dashboard->id}/widgets/{$widget->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertOk();
        $this->assertEquals('Updated Title', $widget->fresh()->title);
    }

    public function test_can_delete_widget(): void
    {
        $dashboard = /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
        ]);
        $widget = /* DashboardWidget factory - use DB::table('dashboard_widgets') */->create([
            'dashboard_id' => $dashboard->id,
        ]);

        $response = $this->deleteJson("/api/v1/dashboards/{$dashboard->id}/widgets/{$widget->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('dashboard_widgets', ['id' => $widget->id]);
    }

    public function test_can_update_widget_positions(): void
    {
        $dashboard = /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
        ]);
        $widget1 = /* DashboardWidget factory - use DB::table('dashboard_widgets') */->create([
            'dashboard_id' => $dashboard->id,
        ]);
        $widget2 = /* DashboardWidget factory - use DB::table('dashboard_widgets') */->create([
            'dashboard_id' => $dashboard->id,
        ]);

        $response = $this->putJson("/api/v1/dashboards/{$dashboard->id}/layout", [
            'widgets' => [
                ['id' => $widget1->id, 'position' => ['x' => 0, 'y' => 0], 'size' => ['w' => 6, 'h' => 4]],
                ['id' => $widget2->id, 'position' => ['x' => 6, 'y' => 0], 'size' => ['w' => 6, 'h' => 4]],
            ],
        ]);

        $response->assertOk();
    }

    // ==========================================
    // Public/Private Tests
    // ==========================================

    public function test_can_toggle_dashboard_public(): void
    {
        $dashboard = /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $this->user->id,
            'is_public' => false,
        ]);

        $response = $this->postJson("/api/v1/dashboards/{$dashboard->id}/toggle-public");

        $response->assertOk();
        $this->assertTrue($dashboard->fresh()->is_public);
    }

    public function test_can_see_public_dashboards_from_other_users(): void
    {
        $otherUser = /* User factory - use DB::table('users') */->create();

        /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $otherUser->id,
            'is_public' => true,
        ]);
        /* Dashboard factory - use DB::table('dashboards') */->create([
            'user_id' => $otherUser->id,
            'is_public' => false,
        ]);

        $response = $this->getJson('/api/v1/dashboards');

        $response->assertOk();
        $dashboards = $response->json('dashboards');
        $otherUserDashboards = array_filter($dashboards, fn($d) => $d['user_id'] === $otherUser->id);
        $this->assertCount(1, $otherUserDashboards);
    }

    // ==========================================
    // Clone Tests
    // ==========================================

    public function test_can_clone_dashboard(): void
    {
        $dashboard = /* Dashboard factory - use DB::table('dashboards') */->create([
            'name' => 'Original Dashboard',
            'user_id' => $this->user->id,
        ]);
        /* DashboardWidget factory - use DB::table('dashboard_widgets') */->count(3)->create([
            'dashboard_id' => $dashboard->id,
        ]);

        $response = $this->postJson("/api/v1/dashboards/{$dashboard->id}/clone");

        $response->assertOk()
            ->assertJsonPath('dashboard.name', 'Original Dashboard (Copy)');

        $this->assertDatabaseCount('dashboards', 2);
        $this->assertDatabaseCount('dashboard_widgets', 6);
    }
}
