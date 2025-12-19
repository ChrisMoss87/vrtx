<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\ModuleRecord;
use App\Models\Field;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->module = Module::factory()->create([
            'name' => 'Leads',
            'singular_name' => 'Lead',
            'api_name' => 'leads',
        ]);

        // Create some fields for the module
        Field::create([
            'module_id' => $this->module->id,
            'label' => 'Name',
            'api_name' => 'name',
            'type' => 'text',
            'is_required' => true,
            'is_searchable' => true,
        ]);

        Field::create([
            'module_id' => $this->module->id,
            'label' => 'Email',
            'api_name' => 'email',
            'type' => 'email',
            'is_searchable' => true,
        ]);

        Field::create([
            'module_id' => $this->module->id,
            'label' => 'Status',
            'api_name' => 'status',
            'type' => 'select',
            'is_filterable' => true,
        ]);
    }

    public function test_can_create_record(): void
    {
        $record = ModuleRecord::create([
            'module_id' => $this->module->id,
            'data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'new',
            ],
        ]);

        $this->assertDatabaseHas('module_records', [
            'id' => $record->id,
            'module_id' => $this->module->id,
        ]);

        $this->assertEquals('John Doe', $record->data['name']);
        $this->assertEquals('john@example.com', $record->data['email']);
    }

    public function test_can_update_record(): void
    {
        $record = ModuleRecord::create([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Original Name'],
        ]);

        $record->update([
            'data' => ['name' => 'Updated Name', 'email' => 'new@example.com'],
        ]);

        $record->refresh();

        $this->assertEquals('Updated Name', $record->data['name']);
        $this->assertEquals('new@example.com', $record->data['email']);
    }

    public function test_can_soft_delete_record(): void
    {
        $record = ModuleRecord::create([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Test Record'],
        ]);

        $recordId = $record->id;
        $record->delete();

        $this->assertSoftDeleted('module_records', ['id' => $recordId]);
        $this->assertNull(ModuleRecord::find($recordId));
        $this->assertNotNull(ModuleRecord::withTrashed()->find($recordId));
    }

    public function test_record_belongs_to_module(): void
    {
        $record = ModuleRecord::create([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Test'],
        ]);

        $this->assertEquals($this->module->id, $record->module->id);
        $this->assertEquals('Leads', $record->module->name);
    }

    public function test_module_has_many_records(): void
    {
        ModuleRecord::create(['module_id' => $this->module->id, 'data' => ['name' => 'Record 1']]);
        ModuleRecord::create(['module_id' => $this->module->id, 'data' => ['name' => 'Record 2']]);
        ModuleRecord::create(['module_id' => $this->module->id, 'data' => ['name' => 'Record 3']]);

        $this->assertCount(3, $this->module->records);
    }

    public function test_search_scope_finds_matching_records(): void
    {
        ModuleRecord::create([
            'module_id' => $this->module->id,
            'data' => ['name' => 'John Doe', 'email' => 'john@example.com'],
        ]);
        ModuleRecord::create([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ]);
        ModuleRecord::create([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Bob Johnson', 'email' => 'bob@example.com'],
        ]);

        // Get searchable fields
        $searchableFields = $this->module->fields()
            ->where('is_searchable', true)
            ->pluck('api_name')
            ->toArray();

        // Search for 'John'
        $results = ModuleRecord::where('module_id', $this->module->id)
            ->search('john', $searchableFields)
            ->get();

        $this->assertCount(2, $results); // John Doe and Bob Johnson
    }

    public function test_data_is_cast_to_array(): void
    {
        $record = ModuleRecord::create([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Test', 'count' => 42],
        ]);

        $this->assertIsArray($record->data);
        $this->assertEquals('Test', $record->data['name']);
        $this->assertEquals(42, $record->data['count']);
    }

    public function test_record_timestamps_are_set(): void
    {
        $record = ModuleRecord::create([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Test'],
        ]);

        $this->assertNotNull($record->created_at);
        $this->assertNotNull($record->updated_at);
    }

    public function test_bulk_create_records(): void
    {
        $recordsData = [
            ['module_id' => $this->module->id, 'data' => ['name' => 'Record 1']],
            ['module_id' => $this->module->id, 'data' => ['name' => 'Record 2']],
            ['module_id' => $this->module->id, 'data' => ['name' => 'Record 3']],
        ];

        foreach ($recordsData as $data) {
            ModuleRecord::create($data);
        }

        $this->assertCount(3, ModuleRecord::where('module_id', $this->module->id)->get());
    }

    public function test_record_with_nested_data(): void
    {
        $record = ModuleRecord::create([
            'module_id' => $this->module->id,
            'data' => [
                'name' => 'Complex Record',
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'New York',
                    'zip' => '10001',
                ],
                'tags' => ['vip', 'priority'],
            ],
        ]);

        $this->assertEquals('Complex Record', $record->data['name']);
        $this->assertEquals('New York', $record->data['address']['city']);
        $this->assertContains('vip', $record->data['tags']);
    }

    public function test_records_are_isolated_per_module(): void
    {
        $otherModule = Module::factory()->create(['api_name' => 'contacts']);

        ModuleRecord::create(['module_id' => $this->module->id, 'data' => ['name' => 'Lead 1']]);
        ModuleRecord::create(['module_id' => $this->module->id, 'data' => ['name' => 'Lead 2']]);
        ModuleRecord::create(['module_id' => $otherModule->id, 'data' => ['name' => 'Contact 1']]);

        $leadRecords = ModuleRecord::where('module_id', $this->module->id)->get();
        $contactRecords = ModuleRecord::where('module_id', $otherModule->id)->get();

        $this->assertCount(2, $leadRecords);
        $this->assertCount(1, $contactRecords);
    }
}
