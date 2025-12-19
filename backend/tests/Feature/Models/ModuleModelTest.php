<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Block;
use App\Models\Field;
use App\Models\Module;
use App\Models\ModuleRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleModelTest extends TestCase
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

    public function test_can_create_module(): void
    {
        $module = Module::create([
            'name' => 'Contacts',
            'singular_name' => 'Contact',
            'api_name' => 'contacts',
            'icon' => 'users',
            'description' => 'Contact management module',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->assertDatabaseHas('modules', [
            'name' => 'Contacts',
            'api_name' => 'contacts',
        ]);

        $this->assertTrue($module->is_active);
        $this->assertEquals(1, $module->display_order);
    }

    public function test_module_has_blocks_relationship(): void
    {
        $module = Module::factory()->create();

        $block = Block::create([
            'module_id' => $module->id,
            'name' => 'Basic Information',
            'type' => 'section',
            'display_order' => 0,
        ]);

        $this->assertCount(1, $module->blocks);
        $this->assertEquals($block->id, $module->blocks->first()->id);
    }

    public function test_module_has_fields_relationship(): void
    {
        $module = Module::factory()->create();

        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'First Name',
            'api_name' => 'first_name',
            'type' => 'text',
            'display_order' => 0,
        ]);

        $this->assertCount(1, $module->fields);
        $this->assertEquals($field->id, $module->fields->first()->id);
    }

    public function test_module_has_records_relationship(): void
    {
        $module = Module::factory()->create();

        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['first_name' => 'John', 'last_name' => 'Doe'],
        ]);

        $this->assertCount(1, $module->records);
        $this->assertEquals($record->id, $module->records->first()->id);
    }

    public function test_module_active_scope(): void
    {
        Module::factory()->create(['is_active' => true, 'api_name' => 'active']);
        Module::factory()->create(['is_active' => false, 'api_name' => 'inactive']);

        $activeModules = Module::active()->get();

        $this->assertCount(1, $activeModules);
        $this->assertEquals('active', $activeModules->first()->api_name);
    }

    public function test_module_ordered_scope(): void
    {
        Module::factory()->create(['api_name' => 'third', 'display_order' => 2]);
        Module::factory()->create(['api_name' => 'first', 'display_order' => 0]);
        Module::factory()->create(['api_name' => 'second', 'display_order' => 1]);

        $modules = Module::ordered()->get();

        $this->assertEquals('first', $modules[0]->api_name);
        $this->assertEquals('second', $modules[1]->api_name);
        $this->assertEquals('third', $modules[2]->api_name);
    }

    public function test_find_by_api_name(): void
    {
        $module = Module::factory()->create(['api_name' => 'contacts']);

        $found = Module::findByApiName('contacts');

        $this->assertNotNull($found);
        $this->assertEquals($module->id, $found->id);
    }

    public function test_find_by_api_name_returns_null_when_not_found(): void
    {
        $found = Module::findByApiName('nonexistent');

        $this->assertNull($found);
    }

    public function test_module_soft_deletes(): void
    {
        $module = Module::factory()->create(['api_name' => 'test']);

        $module->delete();

        $this->assertSoftDeleted('modules', ['api_name' => 'test']);
        $this->assertNull(Module::find($module->id));
        $this->assertNotNull(Module::withTrashed()->find($module->id));
    }

    public function test_module_cascade_deletes_blocks(): void
    {
        $module = Module::factory()->create();
        $block = Block::create([
            'module_id' => $module->id,
            'name' => 'Test Block',
            'type' => 'section',
        ]);

        // Use forceDelete to trigger the database cascade delete
        // Soft deletes do not trigger database cascades
        $module->forceDelete();

        $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
    }

    public function test_module_settings_are_cast_to_array(): void
    {
        $module = Module::factory()->create([
            'settings' => ['color' => 'blue', 'icon_size' => 'large'],
        ]);

        $this->assertIsArray($module->settings);
        $this->assertEquals('blue', $module->settings['color']);
        $this->assertEquals('large', $module->settings['icon_size']);
    }

    public function test_module_default_values(): void
    {
        $module = new Module([
            'name' => 'Test',
            'singular_name' => 'Test',
            'api_name' => 'test',
        ]);

        $this->assertTrue($module->is_active);
        $this->assertEquals(0, $module->display_order);
        // settings has a default of '{}' but is cast to array
        $this->assertEquals([], $module->settings);
    }
}