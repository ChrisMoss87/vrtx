<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineApiTest extends TestCase
{
    use RefreshDatabase;
use Illuminate\Support\Facades\DB;

    protected User $user;
    protected Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = /* User factory - use DB::table('users') */->create();
        $this->module = /* Module factory - use DB::table('modules') */->create([
            'name' => 'Deals',
            'singular_name' => 'Deal',
            'api_name' => 'deals',
        ]);
    }

    public function test_can_create_pipeline(): void
    {
        $pipeline = DB::table('pipelines')->insertGetId([
            'name' => 'Sales Pipeline',
            'module_id' => $this->module->id,
            'stage_field_api_name' => 'stage_id',
            'is_active' => true,
            'settings' => [],
        ]);

        $this->assertDatabaseHas('pipelines', [
            'id' => $pipeline->id,
            'name' => 'Sales Pipeline',
            'module_id' => $this->module->id,
        ]);
    }

    public function test_pipeline_belongs_to_module(): void
    {
        $pipeline = /* Pipeline factory - use DB::table('pipelines') */->create([
            'module_id' => $this->module->id,
        ]);

        $this->assertEquals($this->module->id, $pipeline->module->id);
        $this->assertEquals('Deals', $pipeline->module->name);
    }

    public function test_pipeline_has_many_stages(): void
    {
        $pipeline = /* Pipeline factory - use DB::table('pipelines') */->create([
            'module_id' => $this->module->id,
        ]);

        /* Stage factory - use DB::table('stages') */->count(3)->create([
            'pipeline_id' => $pipeline->id,
        ]);

        $this->assertCount(3, $pipeline->stages);
    }

    public function test_can_create_pipeline_with_stages(): void
    {
        $pipeline = /* Pipeline factory - use DB::table('pipelines') */->withStages()->create([
            'module_id' => $this->module->id,
        ]);

        $this->assertCount(6, $pipeline->stages);
        $this->assertEquals('Lead', $pipeline->stages->first()->name);
    }

    public function test_can_update_pipeline(): void
    {
        $pipeline = /* Pipeline factory - use DB::table('pipelines') */->create([
            'module_id' => $this->module->id,
            'name' => 'Original Name',
        ]);

        $pipeline->update([
            'name' => 'Updated Name',
            'is_active' => false,
        ]);

        $pipeline->refresh();

        $this->assertEquals('Updated Name', $pipeline->name);
        $this->assertFalse($pipeline->is_active);
    }

    public function test_can_soft_delete_pipeline(): void
    {
        $pipeline = /* Pipeline factory - use DB::table('pipelines') */->create([
            'module_id' => $this->module->id,
        ]);

        $pipelineId = $pipeline->id;
        $pipeline->delete();

        $this->assertSoftDeleted('pipelines', ['id' => $pipelineId]);
        $this->assertNull(DB::table('pipelines')->where('id', $pipelineId)->first());
        $this->assertNotNull(Pipeline::withTrashed()->find($pipelineId));
    }

    public function test_active_scope_returns_only_active_pipelines(): void
    {
        /* Pipeline factory - use DB::table('pipelines') */->count(3)->create(['module_id' => $this->module->id, 'is_active' => true]);
        /* Pipeline factory - use DB::table('pipelines') */->count(2)->create(['module_id' => $this->module->id, 'is_active' => false]);

        $activePipelines = Pipeline::active()->get();

        $this->assertCount(3, $activePipelines);
        $activePipelines->each(function ($pipeline) {
            $this->assertTrue($pipeline->is_active);
        });
    }

    public function test_for_module_scope_filters_by_module(): void
    {
        $otherModule = /* Module factory - use DB::table('modules') */->create();

        /* Pipeline factory - use DB::table('pipelines') */->count(2)->create(['module_id' => $this->module->id]);
        /* Pipeline factory - use DB::table('pipelines') */->count(3)->create(['module_id' => $otherModule->id]);

        $modulePipelines = Pipeline::forModule($this->module->id)->get();

        $this->assertCount(2, $modulePipelines);
    }

    public function test_settings_is_cast_to_array(): void
    {
        $pipeline = /* Pipeline factory - use DB::table('pipelines') */->create([
            'module_id' => $this->module->id,
            'settings' => ['show_totals' => true, 'value_field' => 'amount'],
        ]);

        $this->assertIsArray($pipeline->settings);
        $this->assertTrue($pipeline->settings['show_totals']);
        $this->assertEquals('amount', $pipeline->settings['value_field']);
    }
}
