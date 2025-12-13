<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Blueprint;
use App\Models\BlueprintState;
use App\Models\BlueprintTransition;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlueprintApiTest extends TestCase
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

        // Create role with blueprint permissions
        $this->role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $permissions = [
            'blueprints.view',
            'blueprints.create',
            'blueprints.edit',
            'blueprints.delete',
        ];
        foreach ($permissions as $permName) {
            $perm = Permission::create(['name' => $permName, 'guard_name' => 'web']);
            $this->role->givePermissionTo($perm);
        }
        $this->user->assignRole($this->role);

        Sanctum::actingAs($this->user);
    }

    // ==========================================
    // Index / List Tests
    // ==========================================

    public function test_can_list_all_blueprints(): void
    {
        Blueprint::factory()->count(3)->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/blueprints');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'blueprints' => [
                    '*' => ['id', 'name', 'module_id', 'is_active'],
                ],
            ]);
    }

    public function test_can_filter_blueprints_by_module(): void
    {
        $otherModule = Module::factory()->create();

        Blueprint::factory()->count(2)->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        Blueprint::factory()->create([
            'module_id' => $otherModule->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/blueprints?module_id=' . $this->module->id);

        $response->assertOk();
        // Only blueprints for the specified module
        $blueprints = $response->json('blueprints');
        foreach ($blueprints as $bp) {
            $this->assertEquals($this->module->id, $bp['module_id']);
        }
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function test_can_create_blueprint(): void
    {
        $response = $this->postJson('/api/v1/blueprints', [
            'name' => 'Deal Approval Process',
            'description' => 'Approval workflow for deals',
            'module_id' => $this->module->id,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('blueprint.name', 'Deal Approval Process');

        $this->assertDatabaseHas('blueprints', [
            'name' => 'Deal Approval Process',
            'module_id' => $this->module->id,
        ]);
    }

    public function test_cannot_create_blueprint_without_name(): void
    {
        $response = $this->postJson('/api/v1/blueprints', [
            'module_id' => $this->module->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cannot_create_blueprint_without_module(): void
    {
        $response = $this->postJson('/api/v1/blueprints', [
            'name' => 'Test Blueprint',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['module_id']);
    }

    // ==========================================
    // Show Tests
    // ==========================================

    public function test_can_show_blueprint(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/blueprints/{$blueprint->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'blueprint' => [
                    'id' => $blueprint->id,
                    'name' => $blueprint->name,
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_blueprint(): void
    {
        $response = $this->getJson('/api/v1/blueprints/99999');

        $response->assertNotFound();
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function test_can_update_blueprint(): void
    {
        $blueprint = Blueprint::factory()->create([
            'name' => 'Original Name',
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/blueprints/{$blueprint->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('blueprints', [
            'id' => $blueprint->id,
            'name' => 'Updated Name',
        ]);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function test_can_delete_blueprint(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/blueprints/{$blueprint->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('blueprints', ['id' => $blueprint->id]);
    }

    // ==========================================
    // Toggle Active Tests
    // ==========================================

    public function test_can_toggle_blueprint_active_status(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/blueprints/{$blueprint->id}/toggle-active");

        $response->assertOk();
        $this->assertTrue($blueprint->fresh()->is_active);
    }

    // ==========================================
    // States Tests
    // ==========================================

    public function test_can_list_blueprint_states(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        BlueprintState::factory()->count(3)->create([
            'blueprint_id' => $blueprint->id,
        ]);

        $response = $this->getJson("/api/v1/blueprints/{$blueprint->id}/states");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'states' => [
                    '*' => ['id', 'name', 'blueprint_id'],
                ],
            ]);
    }

    public function test_can_create_blueprint_state(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/blueprints/{$blueprint->id}/states", [
            'name' => 'Draft',
            'description' => 'Initial draft state',
            'is_initial' => true,
            'is_final' => false,
            'color' => '#3498db',
        ]);

        $response->assertCreated()
            ->assertJsonPath('state.name', 'Draft');

        $this->assertDatabaseHas('blueprint_states', [
            'blueprint_id' => $blueprint->id,
            'name' => 'Draft',
            'is_initial' => true,
        ]);
    }

    public function test_can_update_blueprint_state(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $state = BlueprintState::factory()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'Original',
        ]);

        $response = $this->putJson("/api/v1/blueprints/{$blueprint->id}/states/{$state->id}", [
            'name' => 'Updated State',
        ]);

        $response->assertOk();
        $this->assertEquals('Updated State', $state->fresh()->name);
    }

    public function test_can_delete_blueprint_state(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $state = BlueprintState::factory()->create([
            'blueprint_id' => $blueprint->id,
        ]);

        $response = $this->deleteJson("/api/v1/blueprints/{$blueprint->id}/states/{$state->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('blueprint_states', ['id' => $state->id]);
    }

    // ==========================================
    // Transitions Tests
    // ==========================================

    public function test_can_list_blueprint_transitions(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $fromState = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id]);
        $toState = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id]);
        BlueprintTransition::factory()->create([
            'blueprint_id' => $blueprint->id,
            'from_state_id' => $fromState->id,
            'to_state_id' => $toState->id,
        ]);

        $response = $this->getJson("/api/v1/blueprints/{$blueprint->id}/transitions");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'transitions' => [
                    '*' => ['id', 'name', 'from_state_id', 'to_state_id'],
                ],
            ]);
    }

    public function test_can_create_blueprint_transition(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $fromState = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id, 'name' => 'Draft']);
        $toState = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id, 'name' => 'Review']);

        $response = $this->postJson("/api/v1/blueprints/{$blueprint->id}/transitions", [
            'name' => 'Submit for Review',
            'from_state_id' => $fromState->id,
            'to_state_id' => $toState->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('transition.name', 'Submit for Review');

        $this->assertDatabaseHas('blueprint_transitions', [
            'blueprint_id' => $blueprint->id,
            'name' => 'Submit for Review',
            'from_state_id' => $fromState->id,
            'to_state_id' => $toState->id,
        ]);
    }

    public function test_cannot_create_transition_to_same_state(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $state = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id]);

        $response = $this->postJson("/api/v1/blueprints/{$blueprint->id}/transitions", [
            'name' => 'Invalid Transition',
            'from_state_id' => $state->id,
            'to_state_id' => $state->id,
        ]);

        $response->assertUnprocessable();
    }

    public function test_can_update_blueprint_transition(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $fromState = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id]);
        $toState = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id]);
        $transition = BlueprintTransition::factory()->create([
            'blueprint_id' => $blueprint->id,
            'from_state_id' => $fromState->id,
            'to_state_id' => $toState->id,
            'name' => 'Original',
        ]);

        $response = $this->putJson("/api/v1/blueprints/{$blueprint->id}/transitions/{$transition->id}", [
            'name' => 'Updated Transition',
        ]);

        $response->assertOk();
        $this->assertEquals('Updated Transition', $transition->fresh()->name);
    }

    public function test_can_delete_blueprint_transition(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $fromState = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id]);
        $toState = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id]);
        $transition = BlueprintTransition::factory()->create([
            'blueprint_id' => $blueprint->id,
            'from_state_id' => $fromState->id,
            'to_state_id' => $toState->id,
        ]);

        $response = $this->deleteJson("/api/v1/blueprints/{$blueprint->id}/transitions/{$transition->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('blueprint_transitions', ['id' => $transition->id]);
    }

    // ==========================================
    // Sync States Tests
    // ==========================================

    public function test_can_sync_blueprint_states(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $existingState = BlueprintState::factory()->create([
            'blueprint_id' => $blueprint->id,
            'name' => 'Existing',
        ]);

        $response = $this->postJson("/api/v1/blueprints/{$blueprint->id}/sync-states", [
            'states' => [
                [
                    'id' => $existingState->id,
                    'name' => 'Updated Existing',
                    'order' => 0,
                ],
                [
                    'name' => 'New State',
                    'order' => 1,
                    'is_initial' => false,
                    'is_final' => true,
                ],
            ],
        ]);

        $response->assertOk();

        $blueprint->refresh();
        $this->assertCount(2, $blueprint->states);
        $this->assertEquals('Updated Existing', $existingState->fresh()->name);
    }

    // ==========================================
    // Layout Update Tests
    // ==========================================

    public function test_can_update_blueprint_layout(): void
    {
        $blueprint = Blueprint::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $state1 = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id]);
        $state2 = BlueprintState::factory()->create(['blueprint_id' => $blueprint->id]);

        $response = $this->putJson("/api/v1/blueprints/{$blueprint->id}/layout", [
            'layout' => [
                'nodes' => [
                    ['id' => $state1->id, 'position' => ['x' => 100, 'y' => 100]],
                    ['id' => $state2->id, 'position' => ['x' => 300, 'y' => 100]],
                ],
            ],
        ]);

        $response->assertOk();
    }
}
