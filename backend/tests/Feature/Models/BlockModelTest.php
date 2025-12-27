<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Modules\Entities\Module;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlockModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize tenancy if needed
        if (config('tenancy.enabled', false)) {
            $this->initializeTenancy();
        }
    }

    public function test_can_create_block(): void
    {
        $module = Module::factory()->create();

        $block = DB::table('blocks')->insertGetId([
            'module_id' => $module->id,
            'name' => 'Contact Information',
            'type' => 'section',
            'display_order' => 0,
        ]);

        $this->assertDatabaseHas('blocks', [
            'module_id' => $module->id,
            'name' => 'Contact Information',
        ]);
    }

    public function test_block_belongs_to_module(): void
    {
        $module = Module::factory()->create();
        $block = DB::table('blocks')->insertGetId([
            'module_id' => $module->id,
            'name' => 'Test Block',
            'type' => 'section',
        ]);

        $this->assertInstanceOf(Module::class, $block->module);
        $this->assertEquals($module->id, $block->module->id);
    }

    public function test_block_has_fields_relationship(): void
    {
        $module = Module::factory()->create();
        $block = DB::table('blocks')->insertGetId([
            'module_id' => $module->id,
            'name' => 'Test Block',
            'type' => 'section',
        ]);

        $field = DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'label' => 'Email',
            'api_name' => 'email',
            'type' => 'email',
        ]);

        $this->assertCount(1, $block->fields);
        $this->assertEquals($field->id, $block->fields->first()->id);
    }

    public function test_block_ordered_scope(): void
    {
        $module = Module::factory()->create();

        DB::table('blocks')->insertGetId(['module_id' => $module->id, 'name' => 'Third', 'type' => 'section', 'display_order' => 2]);
        DB::table('blocks')->insertGetId(['module_id' => $module->id, 'name' => 'First', 'type' => 'section', 'display_order' => 0]);
        DB::table('blocks')->insertGetId(['module_id' => $module->id, 'name' => 'Second', 'type' => 'section', 'display_order' => 1]);

        $blocks = Block::ordered()->get();

        $this->assertEquals('First', $blocks[0]->name);
        $this->assertEquals('Second', $blocks[1]->name);
        $this->assertEquals('Third', $blocks[2]->name);
    }

    public function test_block_settings_are_cast_to_array(): void
    {
        $module = Module::factory()->create();
        $block = DB::table('blocks')->insertGetId([
            'module_id' => $module->id,
            'name' => 'Test',
            'type' => 'section',
            'settings' => ['collapsible' => true, 'collapsed' => false],
        ]);

        $this->assertIsArray($block->settings);
        $this->assertTrue($block->settings['collapsible']);
        $this->assertFalse($block->settings['collapsed']);
    }

    public function test_block_default_values(): void
    {
        $block = new Block([
            'module_id' => 1,
            'name' => 'Test',
        ]);

        $this->assertEquals('section', $block->type);
        $this->assertEquals(0, $block->display_order);
        // settings has a default of '{}' but is cast to array
        $this->assertEquals([], $block->settings);
    }

    public function test_block_cascade_deletes_with_module(): void
    {
        $module = Module::factory()->create();
        $block = DB::table('blocks')->insertGetId([
            'module_id' => $module->id,
            'name' => 'Test Block',
            'type' => 'section',
        ]);

        // Use forceDelete to trigger database cascade
        // Soft deletes do not trigger database cascades
        $module->forceDelete();

        $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
    }

    public function test_block_cascade_deletes_fields(): void
    {
        $module = Module::factory()->create();
        $block = DB::table('blocks')->insertGetId([
            'module_id' => $module->id,
            'name' => 'Test Block',
            'type' => 'section',
        ]);

        $field = DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'label' => 'Test Field',
            'api_name' => 'test_field',
            'type' => 'text',
        ]);

        $block->delete();

        $this->assertDatabaseMissing('fields', ['id' => $field->id]);
    }

    public function test_block_supports_different_types(): void
    {
        $module = Module::factory()->create();

        $types = ['section', 'tab', 'accordion', 'card'];

        foreach ($types as $type) {
            $block = DB::table('blocks')->insertGetId([
                'module_id' => $module->id,
                'name' => ucfirst($type),
                'type' => $type,
            ]);

            $this->assertEquals($type, $block->type);
        }

        $this->assertCount(4, DB::table('blocks')->get());
    }
}
