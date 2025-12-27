<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Domain\User\Entities\User;
use App\Domain\Modules\Entities\Module;
use App\Domain\Reporting\Entities\Report;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Module $module;
    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->module = Module::factory()->create([
            'name' => 'Deals',
            'singular_name' => 'Deal',
            'api_name' => 'deals',
        ]);

        // Create role with report permissions
        $this->role = DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web']);
        $permissions = [
            'reports.view',
            'reports.create',
            'reports.edit',
            'reports.delete',
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

    public function test_can_list_reports(): void
    {
        Report::factory()->count(3)->create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/reports');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'reports' => [
                    '*' => ['id', 'name', 'module_id', 'type'],
                ],
            ]);
    }

    public function test_can_filter_reports_by_module(): void
    {
        $otherModule = Module::factory()->create();

        Report::factory()->count(2)->create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
        ]);
        Report::factory()->create([
            'module_id' => $otherModule->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/reports?module_id=' . $this->module->id);

        $response->assertOk();
        $reports = $response->json('reports');
        foreach ($reports as $report) {
            $this->assertEquals($this->module->id, $report['module_id']);
        }
    }

    public function test_can_filter_reports_by_type(): void
    {
        Report::factory()->count(2)->create([
            'module_id' => $this->module->id,
            'type' => 'tabular',
            'user_id' => $this->user->id,
        ]);
        Report::factory()->create([
            'module_id' => $this->module->id,
            'type' => 'summary',
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/reports?type=tabular');

        $response->assertOk();
        $reports = $response->json('reports');
        foreach ($reports as $report) {
            $this->assertEquals('tabular', $report['type']);
        }
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function test_can_create_report(): void
    {
        $response = $this->postJson('/api/v1/reports', [
            'name' => 'Sales Pipeline Report',
            'description' => 'Monthly sales report',
            'module_id' => $this->module->id,
            'type' => 'tabular',
            'config' => [
                'columns' => ['name', 'amount', 'stage'],
                'filters' => [],
                'sorting' => ['field' => 'created_at', 'direction' => 'desc'],
            ],
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('report.name', 'Sales Pipeline Report');

        $this->assertDatabaseHas('reports', [
            'name' => 'Sales Pipeline Report',
            'module_id' => $this->module->id,
            'type' => 'tabular',
        ]);
    }

    public function test_cannot_create_report_without_name(): void
    {
        $response = $this->postJson('/api/v1/reports', [
            'module_id' => $this->module->id,
            'type' => 'tabular',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cannot_create_report_without_module(): void
    {
        $response = $this->postJson('/api/v1/reports', [
            'name' => 'Test Report',
            'type' => 'tabular',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['module_id']);
    }

    // ==========================================
    // Show Tests
    // ==========================================

    public function test_can_show_report(): void
    {
        $report = Report::factory()->create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/reports/{$report->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'report' => [
                    'id' => $report->id,
                    'name' => $report->name,
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_report(): void
    {
        $response = $this->getJson('/api/v1/reports/99999');

        $response->assertNotFound();
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function test_can_update_report(): void
    {
        $report = Report::factory()->create([
            'name' => 'Original Name',
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/reports/{$report->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'name' => 'Updated Name',
        ]);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function test_can_delete_report(): void
    {
        $report = Report::factory()->create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/reports/{$report->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('reports', ['id' => $report->id]);
    }

    // ==========================================
    // Execute/Run Tests
    // ==========================================

    public function test_can_execute_report(): void
    {
        $report = Report::factory()->create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'type' => 'tabular',
            'config' => [
                'columns' => ['name', 'amount'],
            ],
        ]);

        $response = $this->getJson("/api/v1/reports/{$report->id}/execute");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    // ==========================================
    // Public/Private Tests
    // ==========================================

    public function test_can_toggle_report_public(): void
    {
        $report = Report::factory()->create([
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
            'is_public' => false,
        ]);

        $response = $this->postJson("/api/v1/reports/{$report->id}/toggle-public");

        $response->assertOk();
        $this->assertTrue($report->fresh()->is_public);
    }

    public function test_can_see_public_reports_from_other_users(): void
    {
        $otherUser = User::factory()->create();

        Report::factory()->create([
            'module_id' => $this->module->id,
            'user_id' => $otherUser->id,
            'is_public' => true,
        ]);
        Report::factory()->create([
            'module_id' => $this->module->id,
            'user_id' => $otherUser->id,
            'is_public' => false,
        ]);

        $response = $this->getJson('/api/v1/reports');

        $response->assertOk();
        // Should see the public report but not the private one
        $reports = $response->json('reports');
        $publicReports = array_filter($reports, fn($r) => $r['user_id'] === $otherUser->id);
        $this->assertCount(1, $publicReports);
    }

    // ==========================================
    // Clone Tests
    // ==========================================

    public function test_can_clone_report(): void
    {
        $report = Report::factory()->create([
            'name' => 'Original Report',
            'module_id' => $this->module->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/reports/{$report->id}/clone");

        $response->assertOk()
            ->assertJsonPath('report.name', 'Original Report (Copy)');

        $this->assertDatabaseCount('reports', 2);
    }
}
