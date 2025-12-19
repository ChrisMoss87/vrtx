<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Module;
use App\Models\ModuleRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleRecordModelTest extends TestCase
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

    public function test_can_create_module_record(): void
    {
        $module = Module::factory()->create();

        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ],
        ]);

        $this->assertDatabaseHas('module_records', [
            'module_id' => $module->id,
        ]);

        $this->assertEquals('John', $record->data['first_name']);
    }

    public function test_module_record_belongs_to_module(): void
    {
        $module = Module::factory()->create();
        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['name' => 'Test'],
        ]);

        $this->assertInstanceOf(Module::class, $record->module);
        $this->assertEquals($module->id, $record->module->id);
    }

    public function test_module_record_belongs_to_creator(): void
    {
        $module = Module::factory()->create();
        $user = User::factory()->create();

        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['name' => 'Test'],
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $record->creator);
        $this->assertEquals($user->id, $record->creator->id);
    }

    public function test_module_record_belongs_to_updater(): void
    {
        $module = Module::factory()->create();
        $user = User::factory()->create();

        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['name' => 'Test'],
            'updated_by' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $record->updater);
        $this->assertEquals($user->id, $record->updater->id);
    }

    public function test_get_field_from_data(): void
    {
        $module = Module::factory()->create();
        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'age' => 30,
            ],
        ]);

        $this->assertEquals('John', $record->getField('first_name'));
        $this->assertEquals('Doe', $record->getField('last_name'));
        $this->assertEquals(30, $record->getField('age'));
        $this->assertNull($record->getField('nonexistent'));
    }

    public function test_set_field_in_data(): void
    {
        $module = Module::factory()->create();
        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['name' => 'Original'],
        ]);

        $record->setField('name', 'Updated');
        $record->setField('email', 'test@example.com');

        $this->assertEquals('Updated', $record->data['name']);
        $this->assertEquals('test@example.com', $record->data['email']);
    }

    public function test_module_record_data_is_cast_to_array(): void
    {
        $module = Module::factory()->create();
        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => [
                'name' => 'Test',
                'active' => true,
                'count' => 42,
            ],
        ]);

        $this->assertIsArray($record->data);
        $this->assertEquals('Test', $record->data['name']);
        $this->assertTrue($record->data['active']);
        $this->assertEquals(42, $record->data['count']);
    }

    public function test_module_record_default_values(): void
    {
        $record = new ModuleRecord([
            'module_id' => 1,
        ]);

        // data has a default of '{}' but is cast to array
        $this->assertEquals([], $record->data);
    }

    public function test_module_record_soft_deletes(): void
    {
        $module = Module::factory()->create();
        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['name' => 'Test'],
        ]);

        $record->delete();

        $this->assertSoftDeleted('module_records', ['id' => $record->id]);
        $this->assertNull(ModuleRecord::find($record->id));
        $this->assertNotNull(ModuleRecord::withTrashed()->find($record->id));
    }

    public function test_module_record_cascade_deletes_with_module(): void
    {
        $module = Module::factory()->create();
        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['name' => 'Test'],
        ]);

        // Use forceDelete to trigger database cascade
        // Soft deletes do not trigger database cascades
        $module->forceDelete();

        $this->assertDatabaseMissing('module_records', ['id' => $record->id]);
    }

    public function test_search_scope_finds_matching_records(): void
    {
        $module = Module::factory()->create();

        ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['name' => 'John Doe', 'email' => 'john@example.com'],
        ]);

        ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ]);

        // This test will only work with PostgreSQL due to JSONB operators
        if (config('database.default') === 'pgsql') {
            $results = ModuleRecord::search('John', ['name'])->get();
            $this->assertCount(1, $results);
            $this->assertEquals('John Doe', $results->first()->data['name']);
        } else {
            $this->assertTrue(true, 'Skipping JSONB test for non-PostgreSQL database');
        }
    }

    public function test_where_field_scope_filters_by_field_value(): void
    {
        $module = Module::factory()->create();

        ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['status' => 'active'],
        ]);

        ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['status' => 'inactive'],
        ]);

        // This test will only work with PostgreSQL due to JSONB operators
        if (config('database.default') === 'pgsql') {
            $results = ModuleRecord::whereField('status', '=', 'active')->get();
            $this->assertCount(1, $results);
            $this->assertEquals('active', $results->first()->data['status']);
        } else {
            $this->assertTrue(true, 'Skipping JSONB test for non-PostgreSQL database');
        }
    }

    public function test_order_by_field_scope_sorts_by_field(): void
    {
        $module = Module::factory()->create();

        ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['priority' => '3'],
        ]);

        ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['priority' => '1'],
        ]);

        ModuleRecord::create([
            'module_id' => $module->id,
            'data' => ['priority' => '2'],
        ]);

        // This test will only work with PostgreSQL due to JSONB operators
        if (config('database.default') === 'pgsql') {
            $results = ModuleRecord::orderByField('priority', 'asc')->get();
            $this->assertEquals('1', $results[0]->data['priority']);
            $this->assertEquals('2', $results[1]->data['priority']);
            $this->assertEquals('3', $results[2]->data['priority']);
        } else {
            $this->assertTrue(true, 'Skipping JSONB test for non-PostgreSQL database');
        }
    }

    public function test_complex_nested_data_storage(): void
    {
        $module = Module::factory()->create();

        $record = ModuleRecord::create([
            'module_id' => $module->id,
            'data' => [
                'person' => [
                    'name' => 'John Doe',
                    'age' => 30,
                    'address' => [
                        'street' => '123 Main St',
                        'city' => 'New York',
                        'zip' => '10001',
                    ],
                ],
                'tags' => ['customer', 'vip', 'premium'],
                'metadata' => [
                    'source' => 'web',
                    'campaign' => 'summer2024',
                ],
            ],
        ]);

        $this->assertEquals('John Doe', $record->data['person']['name']);
        $this->assertEquals('New York', $record->data['person']['address']['city']);
        $this->assertCount(3, $record->data['tags']);
        $this->assertEquals('web', $record->data['metadata']['source']);
    }
}
