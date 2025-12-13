<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use Laravel\Sanctum\Sanctum;
use Tests\TenantTestCase;

class WorkflowApiTest extends TenantTestCase
{
    protected User $user;
    protected Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->module = Module::factory()->create([
            'name' => 'Deals',
            'singular_name' => 'Deal',
            'api_name' => 'deals',
        ]);

        Sanctum::actingAs($this->user);
    }

    // ==========================================
    // Index / List Tests
    // ==========================================

    public function test_can_list_all_workflows(): void
    {
        Workflow::factory()->count(3)->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/workflows');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'workflows' => [
                    '*' => ['id', 'name', 'module_id', 'trigger_type', 'is_active'],
                ],
            ])
            ->assertJsonCount(3, 'workflows');
    }

    public function test_can_filter_workflows_by_module(): void
    {
        $otherModule = Module::factory()->create();

        Workflow::factory()->count(2)->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        Workflow::factory()->create([
            'module_id' => $otherModule->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/workflows?module_id=' . $this->module->id);

        $response->assertOk()
            ->assertJsonCount(2, 'workflows');
    }

    public function test_can_filter_workflows_by_active_status(): void
    {
        Workflow::factory()->count(2)->create([
            'module_id' => $this->module->id,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        Workflow::factory()->create([
            'module_id' => $this->module->id,
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/workflows?active=true');

        $response->assertOk()
            ->assertJsonCount(2, 'workflows');
    }

    public function test_can_filter_workflows_by_trigger_type(): void
    {
        Workflow::factory()->create([
            'module_id' => $this->module->id,
            'trigger_type' => 'record_created',
            'created_by' => $this->user->id,
        ]);
        Workflow::factory()->count(2)->create([
            'module_id' => $this->module->id,
            'trigger_type' => 'record_updated',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/workflows?trigger_type=record_created');

        $response->assertOk()
            ->assertJsonCount(1, 'workflows');
    }

    // ==========================================
    // Trigger Types and Action Types Tests
    // ==========================================

    public function test_can_get_trigger_types(): void
    {
        $response = $this->getJson('/api/v1/workflows/trigger-types');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'trigger_types',
            ]);
    }

    public function test_can_get_action_types(): void
    {
        $response = $this->getJson('/api/v1/workflows/action-types');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'action_types',
            ]);
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function test_can_create_workflow(): void
    {
        $response = $this->postJson('/api/v1/workflows', [
            'name' => 'Test Workflow',
            'description' => 'Test description',
            'module_id' => $this->module->id,
            'trigger_type' => 'record_created',
            'is_active' => true,
            'trigger_config' => ['field' => 'status'],
            'conditions' => [
                'logic' => 'and',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'new'],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Workflow created successfully',
            ])
            ->assertJsonPath('workflow.name', 'Test Workflow');

        $this->assertDatabaseHas('workflows', [
            'name' => 'Test Workflow',
            'module_id' => $this->module->id,
            'trigger_type' => 'record_created',
        ]);
    }

    public function test_can_create_workflow_with_steps(): void
    {
        $response = $this->postJson('/api/v1/workflows', [
            'name' => 'Workflow with Steps',
            'trigger_type' => 'record_created',
            'module_id' => $this->module->id,
            'steps' => [
                [
                    'name' => 'Send Email',
                    'action_type' => 'send_email',
                    'action_config' => [
                        'to' => '{{record.email}}',
                        'subject' => 'Welcome',
                        'body' => 'Hello!',
                    ],
                ],
                [
                    'name' => 'Update Field',
                    'action_type' => 'update_field',
                    'action_config' => [
                        'field' => 'status',
                        'value' => 'processed',
                    ],
                ],
            ],
        ]);

        $response->assertCreated();

        $workflow = Workflow::where('name', 'Workflow with Steps')->first();
        $this->assertCount(2, $workflow->steps);
        $this->assertEquals('Send Email', $workflow->steps->first()->name);
    }

    public function test_cannot_create_workflow_without_name(): void
    {
        $response = $this->postJson('/api/v1/workflows', [
            'trigger_type' => 'record_created',
            'module_id' => $this->module->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cannot_create_workflow_without_trigger_type(): void
    {
        $response = $this->postJson('/api/v1/workflows', [
            'name' => 'Test Workflow',
            'module_id' => $this->module->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['trigger_type']);
    }

    public function test_cannot_create_workflow_with_invalid_trigger_type(): void
    {
        $response = $this->postJson('/api/v1/workflows', [
            'name' => 'Test Workflow',
            'trigger_type' => 'invalid_trigger',
            'module_id' => $this->module->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['trigger_type']);
    }

    // ==========================================
    // Show Tests
    // ==========================================

    public function test_can_show_workflow(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/workflows/{$workflow->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'workflow' => [
                    'id' => $workflow->id,
                    'name' => $workflow->name,
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_workflow(): void
    {
        $response = $this->getJson('/api/v1/workflows/99999');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Workflow not found',
            ]);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function test_can_update_workflow(): void
    {
        $workflow = Workflow::factory()->create([
            'name' => 'Original Name',
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/workflows/{$workflow->id}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'is_active' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Workflow updated successfully',
            ])
            ->assertJsonPath('workflow.name', 'Updated Name');

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_update_workflow_steps(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $step = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'name' => 'Original Step',
        ]);

        $response = $this->putJson("/api/v1/workflows/{$workflow->id}", [
            'steps' => [
                [
                    'id' => $step->id,
                    'name' => 'Updated Step',
                    'action_type' => 'send_email',
                    'action_config' => ['to' => 'test@example.com'],
                ],
                [
                    'name' => 'New Step',
                    'action_type' => 'update_field',
                    'action_config' => ['field' => 'status', 'value' => 'done'],
                ],
            ],
        ]);

        $response->assertOk();

        $workflow->refresh();
        $this->assertCount(2, $workflow->steps);
        $this->assertEquals('Updated Step', $workflow->steps->where('id', $step->id)->first()->name);
    }

    public function test_returns_404_when_updating_nonexistent_workflow(): void
    {
        $response = $this->putJson('/api/v1/workflows/99999', [
            'name' => 'Updated Name',
        ]);

        $response->assertNotFound();
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function test_can_delete_workflow(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/workflows/{$workflow->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Workflow deleted successfully',
            ]);

        $this->assertSoftDeleted('workflows', ['id' => $workflow->id]);
    }

    public function test_returns_404_when_deleting_nonexistent_workflow(): void
    {
        $response = $this->deleteJson('/api/v1/workflows/99999');

        $response->assertNotFound();
    }

    // ==========================================
    // Toggle Active Tests
    // ==========================================

    public function test_can_toggle_workflow_active_status(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/workflows/{$workflow->id}/toggle-active");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Workflow activated',
            ])
            ->assertJsonPath('workflow.is_active', true);

        // Toggle again
        $response = $this->postJson("/api/v1/workflows/{$workflow->id}/toggle-active");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Workflow deactivated',
            ])
            ->assertJsonPath('workflow.is_active', false);
    }

    // ==========================================
    // Clone Tests
    // ==========================================

    public function test_can_clone_workflow(): void
    {
        $workflow = Workflow::factory()->create([
            'name' => 'Original Workflow',
            'module_id' => $this->module->id,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        WorkflowStep::factory()->count(2)->create([
            'workflow_id' => $workflow->id,
        ]);

        $response = $this->postJson("/api/v1/workflows/{$workflow->id}/clone");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Workflow cloned successfully',
            ])
            ->assertJsonPath('workflow.name', 'Original Workflow (Copy)')
            ->assertJsonPath('workflow.is_active', false);

        $this->assertDatabaseCount('workflows', 2);
        $this->assertDatabaseCount('workflow_steps', 4);
    }

    public function test_returns_404_when_cloning_nonexistent_workflow(): void
    {
        $response = $this->postJson('/api/v1/workflows/99999/clone');

        $response->assertNotFound();
    }

    // ==========================================
    // Manual Trigger Tests
    // ==========================================

    public function test_can_trigger_workflow_manually(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'allow_manual_trigger' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/workflows/{$workflow->id}/trigger", [
            'record_id' => 1,
            'context_data' => ['key' => 'value'],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Workflow execution triggered',
            ]);

        $this->assertDatabaseHas('workflow_executions', [
            'workflow_id' => $workflow->id,
            'trigger_type' => 'manual',
            'status' => 'pending',
        ]);
    }

    public function test_cannot_trigger_workflow_when_manual_trigger_disabled(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'allow_manual_trigger' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/workflows/{$workflow->id}/trigger");

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'This workflow does not allow manual triggers',
            ]);
    }

    // ==========================================
    // Execution History Tests
    // ==========================================

    public function test_can_get_workflow_executions(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        WorkflowExecution::factory()->count(5)->create([
            'workflow_id' => $workflow->id,
            'triggered_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/workflows/{$workflow->id}/executions");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'executions' => [
                    'data',
                    'current_page',
                    'total',
                ],
            ]);
    }

    public function test_can_filter_executions_by_status(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        WorkflowExecution::factory()->count(2)->create([
            'workflow_id' => $workflow->id,
            'status' => 'completed',
            'triggered_by' => $this->user->id,
        ]);
        WorkflowExecution::factory()->create([
            'workflow_id' => $workflow->id,
            'status' => 'failed',
            'triggered_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/workflows/{$workflow->id}/executions?status=completed");

        $response->assertOk()
            ->assertJsonPath('executions.total', 2);
    }

    public function test_can_show_single_execution(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $execution = WorkflowExecution::factory()->create([
            'workflow_id' => $workflow->id,
            'triggered_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/workflows/{$workflow->id}/executions/{$execution->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'execution' => [
                    'id' => $execution->id,
                    'workflow_id' => $workflow->id,
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_execution(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/workflows/{$workflow->id}/executions/99999");

        $response->assertNotFound();
    }

    // ==========================================
    // Reorder Steps Tests
    // ==========================================

    public function test_can_reorder_workflow_steps(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);
        $step1 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'order' => 0,
        ]);
        $step2 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'order' => 1,
        ]);
        $step3 = WorkflowStep::factory()->create([
            'workflow_id' => $workflow->id,
            'order' => 2,
        ]);

        $response = $this->postJson("/api/v1/workflows/{$workflow->id}/reorder-steps", [
            'steps' => [$step3->id, $step1->id, $step2->id],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Steps reordered successfully',
            ]);

        $this->assertEquals(0, $step3->fresh()->order);
        $this->assertEquals(1, $step1->fresh()->order);
        $this->assertEquals(2, $step2->fresh()->order);
    }

    public function test_cannot_reorder_steps_with_invalid_step_ids(): void
    {
        $workflow = Workflow::factory()->create([
            'module_id' => $this->module->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/workflows/{$workflow->id}/reorder-steps", [
            'steps' => [99999, 99998],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['steps.0', 'steps.1']);
    }
}
