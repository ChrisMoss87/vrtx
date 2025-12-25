<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordApiTest extends TestCase
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
            'name' => 'Leads',
            'singular_name' => 'Lead',
            'api_name' => 'leads',
        ]);

        // Create some fields for the module
        DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Name',
            'api_name' => 'name',
            'type' => 'text',
            'is_required' => true,
            'is_searchable' => true,
        ]);

        DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Email',
            'api_name' => 'email',
            'type' => 'email',
            'is_searchable' => true,
        ]);

        DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Status',
            'api_name' => 'status',
            'type' => 'select',
            'is_filterable' => true,
        ]);
    }

    public function test_can_create_record(): void
    {
        $record = DB::table('module_records')->insertGetId([
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
        $record = DB::table('module_records')->insertGetId([
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
        $record = DB::table('module_records')->insertGetId([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Test Record'],
        ]);

        $recordId = $record->id;
        $record->delete();

        $this->assertSoftDeleted('module_records', ['id' => $recordId]);
        $this->assertNull(DB::table('module_records')->where('id', $recordId)->first());
        $this->assertNotNull(ModuleRecord::withTrashed()->find($recordId));
    }

    public function test_record_belongs_to_module(): void
    {
        $record = DB::table('module_records')->insertGetId([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Test'],
        ]);

        $this->assertEquals($this->module->id, $record->module->id);
        $this->assertEquals('Leads', $record->module->name);
    }

    public function test_module_has_many_records(): void
    {
        DB::table('module_records')->insertGetId(['module_id' => $this->module->id, 'data' => ['name' => 'Record 1']]);
        DB::table('module_records')->insertGetId(['module_id' => $this->module->id, 'data' => ['name' => 'Record 2']]);
        DB::table('module_records')->insertGetId(['module_id' => $this->module->id, 'data' => ['name' => 'Record 3']]);

        $this->assertCount(3, $this->module->records);
    }

    public function test_search_scope_finds_matching_records(): void
    {
        DB::table('module_records')->insertGetId([
            'module_id' => $this->module->id,
            'data' => ['name' => 'John Doe', 'email' => 'john@example.com'],
        ]);
        DB::table('module_records')->insertGetId([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ]);
        DB::table('module_records')->insertGetId([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Bob Johnson', 'email' => 'bob@example.com'],
        ]);

        // Get searchable fields
        $searchableFields = $this->module->fields()
            ->where('is_searchable', true)
            ->pluck('api_name')
            ->toArray();

        // Search for 'John'
        $results = DB::table('module_records')->where('module_id', $this->module->id)
            ->search('john', $searchableFields)
            ->get();

        $this->assertCount(2, $results); // John Doe and Bob Johnson
    }

    public function test_data_is_cast_to_array(): void
    {
        $record = DB::table('module_records')->insertGetId([
            'module_id' => $this->module->id,
            'data' => ['name' => 'Test', 'count' => 42],
        ]);

        $this->assertIsArray($record->data);
        $this->assertEquals('Test', $record->data['name']);
        $this->assertEquals(42, $record->data['count']);
    }

    public function test_record_timestamps_are_set(): void
    {
        $record = DB::table('module_records')->insertGetId([
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
            DB::table('module_records')->insertGetId($data);
        }

        $this->assertCount(3, DB::table('module_records')->where('module_id', $this->module->id)->get());
    }

    public function test_record_with_nested_data(): void
    {
        $record = DB::table('module_records')->insertGetId([
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
        $otherModule = /* Module factory - use DB::table('modules') */->create(['api_name' => 'contacts']);

        DB::table('module_records')->insertGetId(['module_id' => $this->module->id, 'data' => ['name' => 'Lead 1']]);
        DB::table('module_records')->insertGetId(['module_id' => $this->module->id, 'data' => ['name' => 'Lead 2']]);
        DB::table('module_records')->insertGetId(['module_id' => $otherModule->id, 'data' => ['name' => 'Contact 1']]);

        $leadRecords = DB::table('module_records')->where('module_id', $this->module->id)->get();
        $contactRecords = DB::table('module_records')->where('module_id', $otherModule->id)->get();

        $this->assertCount(2, $leadRecords);
        $this->assertCount(1, $contactRecords);
    }
}
