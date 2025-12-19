<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\User;
use App\Models\WizardDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WizardDraftModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_create_wizard_draft(): void
    {
        $draft = WizardDraft::create([
            'user_id' => $this->user->id,
            'wizard_type' => 'module_creation',
            'name' => 'My Module Draft',
            'form_data' => ['moduleName' => 'Contacts'],
            'steps_state' => [
                ['id' => 'step-1', 'title' => 'Basic Info', 'isComplete' => true],
                ['id' => 'step-2', 'title' => 'Fields', 'isComplete' => false],
            ],
            'current_step_index' => 1,
        ]);

        $this->assertDatabaseHas('wizard_drafts', [
            'wizard_type' => 'module_creation',
            'name' => 'My Module Draft',
        ]);

        $this->assertEquals($this->user->id, $draft->user_id);
        $this->assertIsArray($draft->form_data);
        $this->assertIsArray($draft->steps_state);
    }

    public function test_draft_belongs_to_user(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->create();

        $this->assertInstanceOf(User::class, $draft->user);
        $this->assertEquals($this->user->id, $draft->user->id);
    }

    public function test_for_user_scope(): void
    {
        $otherUser = User::factory()->create();

        WizardDraft::factory()->count(3)->forUser($this->user)->create();
        WizardDraft::factory()->count(2)->forUser($otherUser)->create();

        $userDrafts = WizardDraft::forUser($this->user->id)->get();

        $this->assertCount(3, $userDrafts);
    }

    public function test_of_type_scope(): void
    {
        WizardDraft::factory()->forUser($this->user)->type('module_creation')->count(2)->create();
        WizardDraft::factory()->forUser($this->user)->type('record_creation')->count(3)->create();

        $moduleDrafts = WizardDraft::forUser($this->user->id)->ofType('module_creation')->get();
        $recordDrafts = WizardDraft::forUser($this->user->id)->ofType('record_creation')->get();

        $this->assertCount(2, $moduleDrafts);
        $this->assertCount(3, $recordDrafts);
    }

    public function test_for_reference_scope(): void
    {
        WizardDraft::factory()->forUser($this->user)->create(['reference_id' => '123']);
        WizardDraft::factory()->forUser($this->user)->create(['reference_id' => '456']);
        WizardDraft::factory()->forUser($this->user)->create(['reference_id' => null]);

        $drafts = WizardDraft::forUser($this->user->id)->forReference('123')->get();

        $this->assertCount(1, $drafts);
        $this->assertEquals('123', $drafts->first()->reference_id);
    }

    public function test_not_expired_scope(): void
    {
        WizardDraft::factory()->forUser($this->user)->permanent()->create();
        WizardDraft::factory()->forUser($this->user)->create([
            'expires_at' => now()->addDays(10),
        ]);
        WizardDraft::factory()->forUser($this->user)->expired()->create();

        $notExpired = WizardDraft::forUser($this->user->id)->notExpired()->get();

        $this->assertCount(2, $notExpired);
    }

    public function test_expired_scope(): void
    {
        WizardDraft::factory()->forUser($this->user)->permanent()->create();
        WizardDraft::factory()->forUser($this->user)->expired()->count(2)->create();

        $expired = WizardDraft::forUser($this->user->id)->expired()->get();

        $this->assertCount(2, $expired);
    }

    public function test_is_expired_method(): void
    {
        $expiredDraft = WizardDraft::factory()->forUser($this->user)->expired()->create();
        $validDraft = WizardDraft::factory()->forUser($this->user)->create([
            'expires_at' => now()->addDays(10),
        ]);
        $permanentDraft = WizardDraft::factory()->forUser($this->user)->permanent()->create();

        $this->assertTrue($expiredDraft->isExpired());
        $this->assertFalse($validDraft->isExpired());
        $this->assertFalse($permanentDraft->isExpired());
    }

    public function test_display_name_attribute(): void
    {
        $namedDraft = WizardDraft::factory()->forUser($this->user)->create([
            'name' => 'My Custom Draft',
        ]);
        $unnamedDraft = WizardDraft::factory()->forUser($this->user)->create([
            'name' => null,
        ]);

        $this->assertEquals('My Custom Draft', $namedDraft->display_name);
        $this->assertStringContainsString('Draft from', $unnamedDraft->display_name);
    }

    public function test_completion_percentage_attribute(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->create([
            'steps_state' => [
                ['id' => '1', 'isComplete' => true],
                ['id' => '2', 'isComplete' => true],
                ['id' => '3', 'isComplete' => false],
                ['id' => '4', 'isComplete' => false],
            ],
        ]);

        $this->assertEquals(50, $draft->completion_percentage);
    }

    public function test_completion_percentage_with_empty_steps(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->create([
            'steps_state' => [],
        ]);

        $this->assertEquals(0, $draft->completion_percentage);
    }

    public function test_update_draft_method(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->create([
            'current_step_index' => 0,
        ]);

        $newFormData = ['field1' => 'value1', 'field2' => 'value2'];
        $newStepsState = [
            ['id' => '1', 'isComplete' => true],
            ['id' => '2', 'isComplete' => false],
        ];

        $draft->updateDraft($newFormData, $newStepsState, 1);

        $this->assertEquals($newFormData, $draft->form_data);
        $this->assertEquals($newStepsState, $draft->steps_state);
        $this->assertEquals(1, $draft->current_step_index);
    }

    public function test_expires_in_method(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->permanent()->create();

        $draft->expiresIn(30);

        $this->assertNotNull($draft->expires_at);
        $this->assertTrue($draft->expires_at->isFuture());
        // Assert expiration is between 29-30 days from now to account for test execution time
        $this->assertTrue($draft->expires_at->isAfter(now()->addDays(29)));
        $this->assertTrue($draft->expires_at->isBefore(now()->addDays(31)));
    }

    public function test_make_permanent_method(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->create([
            'expires_at' => now()->addDays(10),
        ]);

        $draft->makePermanent();

        $this->assertNull($draft->expires_at);
    }

    public function test_cleanup_expired_static_method(): void
    {
        WizardDraft::factory()->forUser($this->user)->permanent()->count(2)->create();
        WizardDraft::factory()->forUser($this->user)->expired()->count(3)->create();
        WizardDraft::factory()->forUser($this->user)->create([
            'expires_at' => now()->addDays(10),
        ]);

        $deleted = WizardDraft::cleanupExpired();

        $this->assertEquals(3, $deleted);
        $this->assertCount(3, WizardDraft::all());
    }

    public function test_form_data_is_cast_to_array(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->create([
            'form_data' => ['key' => 'value', 'nested' => ['a' => 1, 'b' => 2]],
        ]);

        $draft->refresh();

        $this->assertIsArray($draft->form_data);
        $this->assertEquals('value', $draft->form_data['key']);
        $this->assertEquals(['a' => 1, 'b' => 2], $draft->form_data['nested']);
    }

    public function test_steps_state_is_cast_to_array(): void
    {
        $stepsState = [
            ['id' => '1', 'title' => 'Step 1', 'isComplete' => true],
            ['id' => '2', 'title' => 'Step 2', 'isComplete' => false],
        ];

        $draft = WizardDraft::factory()->forUser($this->user)->create([
            'steps_state' => $stepsState,
        ]);

        $draft->refresh();

        $this->assertIsArray($draft->steps_state);
        $this->assertCount(2, $draft->steps_state);
        $this->assertEquals('Step 1', $draft->steps_state[0]['title']);
    }

    public function test_cascade_delete_on_user_deletion(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->create();
        $draftId = $draft->id;

        $this->user->delete();

        $this->assertDatabaseMissing('wizard_drafts', ['id' => $draftId]);
    }

    public function test_factory_module_creation_state(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->moduleCreation()->create();

        $this->assertEquals('module_creation', $draft->wizard_type);
        $this->assertArrayHasKey('moduleName', $draft->form_data);
    }

    public function test_factory_record_creation_state(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->recordCreation(123)->create();

        $this->assertEquals('record_creation', $draft->wizard_type);
        $this->assertEquals('123', $draft->reference_id);
    }

    public function test_factory_nearly_complete_state(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->nearlyComplete()->create();

        $this->assertEquals(3, $draft->current_step_index);
        $this->assertGreaterThan(50, $draft->completion_percentage);
    }

    public function test_factory_just_started_state(): void
    {
        $draft = WizardDraft::factory()->forUser($this->user)->justStarted()->create();

        $this->assertEquals(0, $draft->current_step_index);
        $this->assertEquals(0, $draft->completion_percentage);
    }
}
