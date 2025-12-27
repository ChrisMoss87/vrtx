<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Modules\Entities\Module;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FieldOptionModelTest extends TestCase
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

    public function test_can_create_field_option(): void
    {
        $module = Module::factory()->create();
        $field = DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'label' => 'Status',
            'api_name' => 'status',
            'type' => 'select',
        ]);

        $option = DB::table('field_options')->insertGetId([
            'field_id' => $field->id,
            'label' => 'Active',
            'value' => 'active',
            'display_order' => 0,
        ]);

        $this->assertDatabaseHas('field_options', [
            'field_id' => $field->id,
            'label' => 'Active',
            'value' => 'active',
        ]);
    }

    public function test_field_option_belongs_to_field(): void
    {
        $module = Module::factory()->create();
        $field = DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'label' => 'Priority',
            'api_name' => 'priority',
            'type' => 'select',
        ]);

        $option = DB::table('field_options')->insertGetId([
            'field_id' => $field->id,
            'label' => 'High',
            'value' => 'high',
        ]);

        $this->assertInstanceOf(Field::class, $option->field);
        $this->assertEquals($field->id, $option->field->id);
    }

    public function test_field_option_active_scope(): void
    {
        $module = Module::factory()->create();
        $field = DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'label' => 'Status',
            'api_name' => 'status',
            'type' => 'select',
        ]);

        DB::table('field_options')->insertGetId([
            'field_id' => $field->id,
            'label' => 'Active',
            'value' => 'active',
            'is_active' => true,
        ]);

        DB::table('field_options')->insertGetId([
            'field_id' => $field->id,
            'label' => 'Inactive',
            'value' => 'inactive',
            'is_active' => false,
        ]);

        $activeOptions = FieldOption::active()->get();

        $this->assertCount(1, $activeOptions);
        $this->assertEquals('active', $activeOptions->first()->value);
    }

    public function test_field_option_ordered_scope(): void
    {
        $module = Module::factory()->create();
        $field = DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'label' => 'Priority',
            'api_name' => 'priority',
            'type' => 'select',
        ]);

        DB::table('field_options')->insertGetId(['field_id' => $field->id, 'label' => 'Low', 'value' => 'low', 'display_order' => 2]);
        DB::table('field_options')->insertGetId(['field_id' => $field->id, 'label' => 'High', 'value' => 'high', 'display_order' => 0]);
        DB::table('field_options')->insertGetId(['field_id' => $field->id, 'label' => 'Medium', 'value' => 'medium', 'display_order' => 1]);

        $options = FieldOption::ordered()->get();

        $this->assertEquals('high', $options[0]->value);
        $this->assertEquals('medium', $options[1]->value);
        $this->assertEquals('low', $options[2]->value);
    }

    public function test_field_option_metadata_is_cast_to_array(): void
    {
        $module = Module::factory()->create();
        $field = DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'label' => 'Status',
            'api_name' => 'status',
            'type' => 'select',
        ]);

        $option = DB::table('field_options')->insertGetId([
            'field_id' => $field->id,
            'label' => 'Active',
            'value' => 'active',
            'metadata' => ['color' => 'green', 'icon' => 'check'],
        ]);

        $this->assertIsArray($option->metadata);
        $this->assertEquals('green', $option->metadata['color']);
        $this->assertEquals('check', $option->metadata['icon']);
    }

    public function test_field_option_default_values(): void
    {
        $option = new FieldOption([
            'field_id' => 1,
            'label' => 'Test',
            'value' => 'test',
        ]);

        $this->assertEquals(0, $option->display_order);
        $this->assertTrue($option->is_active);
        // metadata has a default of '{}' but is cast to array
        $this->assertEquals([], $option->metadata);
    }

    public function test_field_option_cascade_deletes_with_field(): void
    {
        $module = Module::factory()->create();
        $field = DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'label' => 'Status',
            'api_name' => 'status',
            'type' => 'select',
        ]);

        $option = DB::table('field_options')->insertGetId([
            'field_id' => $field->id,
            'label' => 'Active',
            'value' => 'active',
        ]);

        $field->delete();

        $this->assertDatabaseMissing('field_options', ['id' => $option->id]);
    }

    public function test_field_option_supports_color(): void
    {
        $module = Module::factory()->create();
        $field = DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'label' => 'Status',
            'api_name' => 'status',
            'type' => 'select',
        ]);

        $option = DB::table('field_options')->insertGetId([
            'field_id' => $field->id,
            'label' => 'Active',
            'value' => 'active',
            'metadata' => ['color' => '#00FF00'],
        ]);

        $this->assertEquals('#00FF00', $option->metadata['color']);
    }

    public function test_multiple_options_for_single_field(): void
    {
        $module = Module::factory()->create();
        $field = DB::table('fields')->insertGetId([
            'module_id' => $module->id,
            'label' => 'Status',
            'api_name' => 'status',
            'type' => 'select',
        ]);

        $values = ['new', 'in_progress', 'completed', 'cancelled'];

        foreach ($values as $index => $value) {
            DB::table('field_options')->insertGetId([
                'field_id' => $field->id,
                'label' => ucfirst(str_replace('_', ' ', $value)),
                'value' => $value,
                'display_order' => $index,
            ]);
        }

        $this->assertCount(4, $field->options);
        $this->assertEquals('new', $field->options->first()->value);
    }
}
