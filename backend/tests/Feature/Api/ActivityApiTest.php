<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Activity\Entities\Activity;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Entities\ModuleRecord;
use App\Domain\User\Entities\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Module $module;
    protected ModuleRecord $record;
    protected int $roleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->module = Module::factory()->create([
            'name' => 'Contacts',
            'singular_name' => 'Contact',
            'api_name' => 'contacts',
        ]);
        $this->record = ModuleRecord::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        // Create role with activity permissions
        $this->roleId = DB::table('roles')->insertGetId(['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        $permissions = [
            'activity.view',
            'activity.create',
            'activity.edit',
            'activity.delete',
        ];
        foreach ($permissions as $permName) {
            $permId = DB::table('permissions')->insertGetId(['name' => $permName, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_has_permissions')->insert(['role_id' => $this->roleId, 'permission_id' => $permId]);
        }
        DB::table('model_has_roles')->insert([
            'role_id' => $this->roleId,
            'model_type' => User::class,
            'model_id' => $this->user->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    // ==========================================
    // Types and Outcomes Tests
    // ==========================================

    public function test_can_get_activity_types(): void
    {
        $response = $this->getJson('/api/v1/activities/types');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'types',
            ]);
    }

    public function test_can_get_activity_outcomes(): void
    {
        $response = $this->getJson('/api/v1/activities/outcomes');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'outcomes',
            ]);
    }

    // ==========================================
    // Index / List Tests
    // ==========================================

    public function test_can_list_activities(): void
    {
        Activity::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->getJson('/api/v1/activities');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'activities' => [
                    'data',
                    'current_page',
                    'total',
                ],
            ]);
    }

    public function test_can_filter_activities_by_type(): void
    {
        Activity::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'type' => 'call',
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);
        Activity::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'meeting',
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->getJson('/api/v1/activities?type=call');

        $response->assertOk();
        $activities = $response->json('activities.data');
        foreach ($activities as $activity) {
            $this->assertEquals('call', $activity['type']);
        }
    }

    public function test_can_get_timeline(): void
    {
        Activity::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->getJson('/api/v1/activities/timeline?subject_type=App\\Models\\ModuleRecord&subject_id=' . $this->record->id);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'timeline',
            ]);
    }

    public function test_can_get_upcoming_activities(): void
    {
        Activity::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'scheduled_at' => now()->addDays(2),
            'completed_at' => null,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->getJson('/api/v1/activities/upcoming');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'activities',
            ]);
    }

    public function test_can_get_overdue_activities(): void
    {
        Activity::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'scheduled_at' => now()->subDays(2),
            'completed_at' => null,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->getJson('/api/v1/activities/overdue');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'activities',
            ]);
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function test_can_create_activity(): void
    {
        $response = $this->postJson('/api/v1/activities', [
            'type' => 'call',
            'title' => 'Sales Call',
            'description' => 'Initial discovery call',
            'subject_type' => 'App\\Models\\ModuleRecord',
            'subject_id' => $this->record->id,
            'scheduled_at' => now()->addDays(1)->toISOString(),
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('activity.title', 'Sales Call');

        $this->assertDatabaseHas('activities', [
            'title' => 'Sales Call',
            'type' => 'call',
        ]);
    }

    public function test_cannot_create_activity_without_type(): void
    {
        $response = $this->postJson('/api/v1/activities', [
            'title' => 'Test Activity',
            'subject_type' => 'App\\Models\\ModuleRecord',
            'subject_id' => $this->record->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_cannot_create_activity_without_title(): void
    {
        $response = $this->postJson('/api/v1/activities', [
            'type' => 'call',
            'subject_type' => 'App\\Models\\ModuleRecord',
            'subject_id' => $this->record->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    // ==========================================
    // Show Tests
    // ==========================================

    public function test_can_show_activity(): void
    {
        $activity = Activity::factory()->create([
            'user_id' => $this->user->id,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->getJson("/api/v1/activities/{$activity->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'activity' => [
                    'id' => $activity->id,
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_activity(): void
    {
        $response = $this->getJson('/api/v1/activities/99999');

        $response->assertNotFound();
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function test_can_update_activity(): void
    {
        $activity = Activity::factory()->create([
            'title' => 'Original Title',
            'user_id' => $this->user->id,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->putJson("/api/v1/activities/{$activity->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'title' => 'Updated Title',
        ]);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function test_can_delete_activity(): void
    {
        $activity = Activity::factory()->create([
            'user_id' => $this->user->id,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->deleteJson("/api/v1/activities/{$activity->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    // ==========================================
    // Complete Tests
    // ==========================================

    public function test_can_complete_activity(): void
    {
        $activity = Activity::factory()->create([
            'user_id' => $this->user->id,
            'completed_at' => null,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->postJson("/api/v1/activities/{$activity->id}/complete", [
            'outcome' => 'completed',
            'notes' => 'Successfully completed',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertNotNull($activity->fresh()->completed_at);
    }

    public function test_can_complete_activity_with_outcome(): void
    {
        $activity = Activity::factory()->create([
            'type' => 'call',
            'user_id' => $this->user->id,
            'completed_at' => null,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->postJson("/api/v1/activities/{$activity->id}/complete", [
            'outcome' => 'no_answer',
        ]);

        $response->assertOk();
        $this->assertEquals('no_answer', $activity->fresh()->outcome);
    }

    // ==========================================
    // Toggle Pin Tests
    // ==========================================

    public function test_can_toggle_activity_pin(): void
    {
        $activity = Activity::factory()->create([
            'user_id' => $this->user->id,
            'is_pinned' => false,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $response = $this->postJson("/api/v1/activities/{$activity->id}/toggle-pin");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue($activity->fresh()->is_pinned);

        // Toggle again
        $response = $this->postJson("/api/v1/activities/{$activity->id}/toggle-pin");
        $this->assertFalse($activity->fresh()->is_pinned);
    }

    // ==========================================
    // Related Record Tests
    // ==========================================

    public function test_can_filter_activities_by_related_record(): void
    {
        $otherRecord = ModuleRecord::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        Activity::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);
        Activity::factory()->create([
            'user_id' => $this->user->id,
            'subject_type' => ModuleRecord::class,
            'subject_id' => $otherRecord->id,
        ]);

        $response = $this->getJson('/api/v1/activities?subject_type=App\\Models\\ModuleRecord&subject_id=' . $this->record->id);

        $response->assertOk();
        $activities = $response->json('activities.data');
        foreach ($activities as $activity) {
            $this->assertEquals($this->record->id, $activity['subject_id']);
        }
    }

    // ==========================================
    // Date Range Filtering Tests
    // ==========================================

    public function test_can_filter_activities_by_date_range(): void
    {
        Activity::factory()->create([
            'user_id' => $this->user->id,
            'scheduled_at' => now()->subDays(5),
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);
        Activity::factory()->create([
            'user_id' => $this->user->id,
            'scheduled_at' => now()->addDays(5),
            'subject_type' => ModuleRecord::class,
            'subject_id' => $this->record->id,
        ]);

        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->getJson("/api/v1/activities?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
    }
}
