<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageApiTest extends TestCase
{
    use RefreshDatabase;
use Illuminate\Support\Facades\DB;

    protected User $user;
    protected Module $module;
    protected Pipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = /* User factory - use DB::table('users') */->create();
        $this->module = /* Module factory - use DB::table('modules') */->create([
            'name' => 'Deals',
            'api_name' => 'deals',
        ]);
        $this->pipeline = /* Pipeline factory - use DB::table('pipelines') */->create([
            'module_id' => $this->module->id,
            'name' => 'Sales Pipeline',
        ]);
    }

    public function test_can_create_stage(): void
    {
        $stage = DB::table('stages')->insertGetId([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Lead',
            'color' => '#3b82f6',
            'probability' => 10,
            'display_order' => 0,
        ]);

        $this->assertDatabaseHas('stages', [
            'id' => $stage->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Lead',
        ]);
    }

    public function test_stage_belongs_to_pipeline(): void
    {
        $stage = /* Stage factory - use DB::table('stages') */->create([
            'pipeline_id' => $this->pipeline->id,
        ]);

        $this->assertEquals($this->pipeline->id, $stage->pipeline->id);
        $this->assertEquals('Sales Pipeline', $stage->pipeline->name);
    }

    public function test_can_update_stage(): void
    {
        $stage = /* Stage factory - use DB::table('stages') */->create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Original Name',
            'probability' => 25,
        ]);

        $stage->update([
            'name' => 'Updated Name',
            'probability' => 50,
        ]);

        $stage->refresh();

        $this->assertEquals('Updated Name', $stage->name);
        $this->assertEquals(50, $stage->probability);
    }

    public function test_can_soft_delete_stage(): void
    {
        $stage = /* Stage factory - use DB::table('stages') */->create([
            'pipeline_id' => $this->pipeline->id,
        ]);

        $stageId = $stage->id;
        $stage->delete();

        $this->assertSoftDeleted('stages', ['id' => $stageId]);
        $this->assertNull(DB::table('stages')->where('id', $stageId)->first());
        $this->assertNotNull(Stage::withTrashed()->find($stageId));
    }

    public function test_ordered_scope_orders_by_display_order(): void
    {
        /* Stage factory - use DB::table('stages') */->create(['pipeline_id' => $this->pipeline->id, 'name' => 'Stage C', 'display_order' => 3]);
        /* Stage factory - use DB::table('stages') */->create(['pipeline_id' => $this->pipeline->id, 'name' => 'Stage A', 'display_order' => 1]);
        /* Stage factory - use DB::table('stages') */->create(['pipeline_id' => $this->pipeline->id, 'name' => 'Stage B', 'display_order' => 2]);

        $orderedStages = DB::table('stages')->where('pipeline_id', $this->pipeline->id)->ordered()->get();

        $this->assertEquals('Stage A', $orderedStages[0]->name);
        $this->assertEquals('Stage B', $orderedStages[1]->name);
        $this->assertEquals('Stage C', $orderedStages[2]->name);
    }

    public function test_won_stage_state(): void
    {
        $stage = /* Stage factory - use DB::table('stages') */->won()->create([
            'pipeline_id' => $this->pipeline->id,
        ]);

        $this->assertTrue($stage->is_won_stage);
        $this->assertFalse($stage->is_lost_stage);
        $this->assertEquals(100, $stage->probability);
    }

    public function test_lost_stage_state(): void
    {
        $stage = /* Stage factory - use DB::table('stages') */->lost()->create([
            'pipeline_id' => $this->pipeline->id,
        ]);

        $this->assertTrue($stage->is_lost_stage);
        $this->assertFalse($stage->is_won_stage);
        $this->assertEquals(0, $stage->probability);
    }

    public function test_settings_is_cast_to_array(): void
    {
        $stage = /* Stage factory - use DB::table('stages') */->create([
            'pipeline_id' => $this->pipeline->id,
            'settings' => ['time_limit_days' => 7, 'required_fields' => ['amount', 'close_date']],
        ]);

        $this->assertIsArray($stage->settings);
        $this->assertEquals(7, $stage->settings['time_limit_days']);
        $this->assertContains('amount', $stage->settings['required_fields']);
    }

    public function test_default_values_are_set(): void
    {
        $stage = DB::table('stages')->insertGetId([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Test Stage',
        ]);

        $this->assertEquals('#6b7280', $stage->color);
        $this->assertEquals(0, $stage->probability);
        $this->assertEquals(0, $stage->display_order);
        $this->assertFalse($stage->is_won_stage);
        $this->assertFalse($stage->is_lost_stage);
    }
}
