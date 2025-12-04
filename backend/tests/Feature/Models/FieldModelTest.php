<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Domain\Modules\ValueObjects\ConditionalVisibility;
use App\Domain\Modules\ValueObjects\DependencyFilter;
use App\Domain\Modules\ValueObjects\FieldDependency;
use App\Domain\Modules\ValueObjects\FormulaDefinition;
use App\Domain\Modules\ValueObjects\LookupConfiguration;
use App\Domain\Modules\ValueObjects\ValidationRule;
use App\Models\Block;
use App\Models\Field;
use App\Models\FieldOption;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldModelTest extends TestCase
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

    public function test_can_create_field(): void
    {
        $module = Module::factory()->create();

        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'First Name',
            'api_name' => 'first_name',
            'type' => 'text',
            'is_required' => true,
        ]);

        $this->assertDatabaseHas('fields', [
            'module_id' => $module->id,
            'label' => 'First Name',
            'api_name' => 'first_name',
        ]);
    }

    public function test_field_belongs_to_module(): void
    {
        $module = Module::factory()->create();
        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'Email',
            'api_name' => 'email',
            'type' => 'email',
        ]);

        $this->assertInstanceOf(Module::class, $field->module);
        $this->assertEquals($module->id, $field->module->id);
    }

    public function test_field_belongs_to_block(): void
    {
        $module = Module::factory()->create();
        $block = Block::create([
            'module_id' => $module->id,
            'name' => 'Basic Info',
            'type' => 'section',
        ]);

        $field = Field::create([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'label' => 'Name',
            'api_name' => 'name',
            'type' => 'text',
        ]);

        $this->assertInstanceOf(Block::class, $field->block);
        $this->assertEquals($block->id, $field->block->id);
    }

    public function test_field_has_options_relationship(): void
    {
        $module = Module::factory()->create();
        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'Status',
            'api_name' => 'status',
            'type' => 'select',
        ]);

        FieldOption::create([
            'field_id' => $field->id,
            'label' => 'Active',
            'value' => 'active',
        ]);

        FieldOption::create([
            'field_id' => $field->id,
            'label' => 'Inactive',
            'value' => 'inactive',
        ]);

        $this->assertCount(2, $field->options);
    }

    public function test_conditional_visibility_object_accessor(): void
    {
        $module = Module::factory()->create();
        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'Conditional Field',
            'api_name' => 'conditional_field',
            'type' => 'text',
            'conditional_visibility' => [
                'enabled' => true,
                'operator' => 'and',
                'conditions' => [
                    [
                        'field' => 'status',
                        'operator' => 'equals',
                        'value' => 'active',
                    ],
                ],
            ],
        ]);

        $visibility = $field->conditionalVisibilityObject;

        $this->assertInstanceOf(ConditionalVisibility::class, $visibility);
        $this->assertTrue($visibility->isEnabled());
        $this->assertEquals('and', $visibility->operator);
    }

    public function test_validation_rule_object_accessor(): void
    {
        $module = Module::factory()->create();
        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'Email',
            'api_name' => 'email',
            'type' => 'email',
            'validation_rules' => ['required', 'email', 'max:255'],
        ]);

        $validation = $field->validationRulesObject;

        $this->assertInstanceOf(ValidationRule::class, $validation);
        $this->assertTrue($validation->isRequired());
        $this->assertContains('email', $validation->rules);
    }

    public function test_lookup_configuration_object_accessor(): void
    {
        $module = Module::factory()->create();
        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'Related Contact',
            'api_name' => 'contact_id',
            'type' => 'lookup',
            'lookup_settings' => [
                'related_module_id' => 1,
                'related_module_name' => 'contacts',
                'relationship_type' => 'many_to_one',
                'display_field' => 'name',
                'allow_create' => true,
            ],
        ]);

        $lookup = $field->lookupSettingsObject;

        $this->assertInstanceOf(LookupConfiguration::class, $lookup);
        $this->assertEquals('contacts', $lookup->relatedModuleName);
        $this->assertEquals('many_to_one', $lookup->relationshipType);
        $this->assertTrue($lookup->allowCreate);
    }

    public function test_has_conditional_visibility(): void
    {
        $module = Module::factory()->create();

        // Field with conditions - should have visibility enabled
        $fieldWithVisibility = Field::create([
            'module_id' => $module->id,
            'label' => 'Field 1',
            'api_name' => 'field_1',
            'type' => 'text',
            'conditional_visibility' => [
                'enabled' => true,
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
                ],
            ],
        ]);

        $fieldWithoutVisibility = Field::create([
            'module_id' => $module->id,
            'label' => 'Field 2',
            'api_name' => 'field_2',
            'type' => 'text',
        ]);

        $this->assertTrue($fieldWithVisibility->hasConditionalVisibility());
        $this->assertFalse($fieldWithoutVisibility->hasConditionalVisibility());
    }

    public function test_is_formula_field(): void
    {
        $module = Module::factory()->create();

        $formulaField = Field::create([
            'module_id' => $module->id,
            'label' => 'Total',
            'api_name' => 'total',
            'type' => 'formula',
            'formula_definition' => [
                'formula' => '{quantity} * {price}',
                'formula_type' => 'calculation',
                'return_type' => 'currency',
                'dependencies' => ['quantity', 'price'],
            ],
        ]);

        $regularField = Field::create([
            'module_id' => $module->id,
            'label' => 'Name',
            'api_name' => 'name',
            'type' => 'text',
        ]);

        $this->assertTrue($formulaField->isFormulaField());
        $this->assertFalse($regularField->isFormulaField());
    }

    public function test_is_lookup_field(): void
    {
        $module = Module::factory()->create();

        $lookupField = Field::create([
            'module_id' => $module->id,
            'label' => 'Contact',
            'api_name' => 'contact_id',
            'type' => 'lookup',
            'lookup_settings' => [
                'target_module' => 'contacts',
                'relationship_type' => 'many_to_one',
            ],
        ]);

        $regularField = Field::create([
            'module_id' => $module->id,
            'label' => 'Name',
            'api_name' => 'name',
            'type' => 'text',
        ]);

        $this->assertTrue($lookupField->isLookupField());
        $this->assertFalse($regularField->isLookupField());
    }

    public function test_get_dependencies(): void
    {
        $module = Module::factory()->create();

        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'Conditional Field',
            'api_name' => 'conditional_field',
            'type' => 'text',
            'conditional_visibility' => [
                'enabled' => true,
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
                    ['field' => 'type', 'operator' => 'equals', 'value' => 'premium'],
                ],
            ],
        ]);

        $dependencies = $field->getDependencies();

        $this->assertIsArray($dependencies);
        $this->assertContains('status', $dependencies);
        $this->assertContains('type', $dependencies);
    }

    public function test_is_visible(): void
    {
        $module = Module::factory()->create();

        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'Conditional Field',
            'api_name' => 'conditional_field',
            'type' => 'text',
            'conditional_visibility' => [
                'enabled' => true,
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
                ],
            ],
        ]);

        $this->assertTrue($field->isVisible(['status' => 'active']));
        $this->assertFalse($field->isVisible(['status' => 'inactive']));
    }

    public function test_get_validation_rules(): void
    {
        $module = Module::factory()->create();

        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'Email',
            'api_name' => 'email',
            'type' => 'email',
            'is_required' => true,
            'is_unique' => true,
            'validation_rules' => ['email', 'max:255'],
        ]);

        $rules = $field->getValidationRules();

        $this->assertIsArray($rules);
        // required is added when is_required=true
        $this->assertContains('required', $rules);
        // email comes from validation_rules
        $this->assertContains('email', $rules);
        // unique is added when is_unique=true
        $this->assertTrue(str_contains(implode(',', $rules), 'unique'));
    }

    public function test_field_cascade_deletes_with_module(): void
    {
        $module = Module::factory()->create();
        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'Test Field',
            'api_name' => 'test_field',
            'type' => 'text',
        ]);

        // Use forceDelete to trigger database cascade
        // Soft deletes do not trigger database cascades
        $module->forceDelete();

        $this->assertDatabaseMissing('fields', ['id' => $field->id]);
    }

    public function test_field_cascade_deletes_with_block(): void
    {
        $module = Module::factory()->create();
        $block = Block::create([
            'module_id' => $module->id,
            'name' => 'Test Block',
            'type' => 'section',
        ]);

        $field = Field::create([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'label' => 'Test Field',
            'api_name' => 'test_field',
            'type' => 'text',
        ]);

        $block->delete();

        $this->assertDatabaseMissing('fields', ['id' => $field->id]);
    }

    public function test_field_cascade_deletes_options(): void
    {
        $module = Module::factory()->create();
        $field = Field::create([
            'module_id' => $module->id,
            'label' => 'Status',
            'api_name' => 'status',
            'type' => 'select',
        ]);

        $option = FieldOption::create([
            'field_id' => $field->id,
            'label' => 'Active',
            'value' => 'active',
        ]);

        $field->delete();

        $this->assertDatabaseMissing('field_options', ['id' => $option->id]);
    }

    public function test_unique_api_name_per_module(): void
    {
        $module = Module::factory()->create();

        Field::create([
            'module_id' => $module->id,
            'label' => 'Name 1',
            'api_name' => 'name',
            'type' => 'text',
        ]);

        $this->expectException(\Exception::class);

        Field::create([
            'module_id' => $module->id,
            'label' => 'Name 2',
            'api_name' => 'name',
            'type' => 'text',
        ]);
    }

    public function test_all_field_types_supported(): void
    {
        $module = Module::factory()->create();

        $types = [
            'text', 'email', 'phone', 'url', 'textarea', 'rich_text',
            'number', 'currency', 'percent', 'date', 'datetime', 'time',
            'checkbox', 'select', 'multiselect', 'radio', 'lookup',
            'file', 'image', 'formula', 'auto_number'
        ];

        foreach ($types as $index => $type) {
            $field = Field::create([
                'module_id' => $module->id,
                'label' => ucfirst($type),
                'api_name' => $type . '_' . $index,
                'type' => $type,
            ]);

            $this->assertEquals($type, $field->type);
        }

        $this->assertCount(count($types), Field::all());
    }
}
