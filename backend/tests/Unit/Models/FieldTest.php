<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Domain\Modules\ValueObjects\ConditionalVisibility;
use App\Domain\Modules\ValueObjects\FieldDependency;
use App\Domain\Modules\ValueObjects\FormulaDefinition;
use App\Domain\Modules\ValueObjects\LookupConfiguration;
use App\Domain\Modules\ValueObjects\ValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FieldTest extends TestCase
{
    use RefreshDatabase;

    protected $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = DB::table('modules')->insertGetId([
            'name' => 'Test Module',
            'singular_name' => 'Test',
            'api_name' => 'test_module',
            'icon' => 'test',
        ]);
    }

    public function test_field_can_be_created(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Test Field',
            'api_name' => 'test_field',
            'type' => 'text',
        ]);

        $this->assertDatabaseHas('fields', [
            'label' => 'Test Field',
            'api_name' => 'test_field',
            'type' => 'text',
        ]);

        $this->assertEquals('Test Field', $field->label);
    }

    public function test_field_belongs_to_module(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Test Field',
            'api_name' => 'test_field',
            'type' => 'text',
        ]);

        $this->assertInstanceOf(Module::class, $field->module);
        $this->assertEquals($this->module->id, $field->module->id);
    }

    public function test_requires_options_returns_true_for_select_types(): void
    {
        $selectField = new Field(['type' => 'select']);
        $multiselectField = new Field(['type' => 'multiselect']);
        $radioField = new Field(['type' => 'radio']);
        $textField = new Field(['type' => 'text']);

        $this->assertTrue($selectField->requiresOptions());
        $this->assertTrue($multiselectField->requiresOptions());
        $this->assertTrue($radioField->requiresOptions());
        $this->assertFalse($textField->requiresOptions());
    }

    public function test_conditional_visibility_object_returns_value_object(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Test Field',
            'api_name' => 'test_field',
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
        $this->assertTrue($visibility->enabled);
        $this->assertEquals('and', $visibility->operator);
    }

    public function test_conditional_visibility_object_returns_disabled_when_null(): void
    {
        $field = new Field([
            'conditional_visibility' => null,
        ]);

        $visibility = $field->conditionalVisibilityObject;

        $this->assertInstanceOf(ConditionalVisibility::class, $visibility);
        $this->assertFalse($visibility->enabled);
    }

    public function test_formula_definition_object_returns_value_object(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Total',
            'api_name' => 'total',
            'type' => 'formula',
            'formula_definition' => [
                'formula' => 'amount * quantity',
                'formula_type' => 'calculation',
                'return_type' => 'number',
                'dependencies' => ['amount', 'quantity'],
                'recalculate_on' => ['amount', 'quantity'],
            ],
        ]);

        $formula = $field->formulaDefinitionObject;

        $this->assertInstanceOf(FormulaDefinition::class, $formula);
        $this->assertEquals('amount * quantity', $formula->formula);
        $this->assertEquals('calculation', $formula->formulaType);
    }

    public function test_lookup_settings_object_returns_value_object(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Contact',
            'api_name' => 'contact_id',
            'type' => 'lookup',
            'lookup_settings' => [
                'related_module_id' => 1,
                'related_module_name' => 'contacts',
                'display_field' => 'full_name',
                'search_fields' => ['first_name', 'last_name'],
                'allow_create' => true,
                'relationship_type' => 'many_to_one',
            ],
        ]);

        $lookup = $field->lookupSettingsObject;

        $this->assertInstanceOf(LookupConfiguration::class, $lookup);
        $this->assertEquals('contacts', $lookup->relatedModuleName);
        $this->assertEquals('full_name', $lookup->displayField);
    }

    public function test_validation_rules_object_returns_value_object(): void
    {
        $field = new Field([
            'validation_rules' => ['required', 'string', 'max:255'],
        ]);

        $rules = $field->validationRulesObject;

        $this->assertInstanceOf(ValidationRule::class, $rules);
        $this->assertTrue($rules->hasRule('required'));
        $this->assertTrue($rules->hasRule('string'));
    }

    public function test_has_conditional_visibility(): void
    {
        $fieldWithVisibility = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
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

        $fieldWithoutVisibility = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Field 2',
            'api_name' => 'field_2',
            'type' => 'text',
        ]);

        $this->assertTrue($fieldWithVisibility->hasConditionalVisibility());
        $this->assertFalse($fieldWithoutVisibility->hasConditionalVisibility());
    }

    public function test_is_formula_field(): void
    {
        $formulaField = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Total',
            'api_name' => 'total',
            'type' => 'formula',
            'formula_definition' => [
                'formula' => 'amount * quantity',
                'formula_type' => 'calculation',
                'return_type' => 'number',
                'dependencies' => ['amount', 'quantity'],
                'recalculate_on' => ['amount', 'quantity'],
            ],
        ]);

        $textField = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Name',
            'api_name' => 'name',
            'type' => 'text',
        ]);

        $this->assertTrue($formulaField->isFormulaField());
        $this->assertFalse($textField->isFormulaField());
    }

    public function test_is_lookup_field(): void
    {
        $lookupField = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Contact',
            'api_name' => 'contact_id',
            'type' => 'lookup',
            'lookup_settings' => [
                'related_module_id' => 1,
                'related_module_name' => 'contacts',
                'display_field' => 'full_name',
                'search_fields' => ['first_name', 'last_name'],
            ],
        ]);

        $textField = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Name',
            'api_name' => 'name',
            'type' => 'text',
        ]);

        $this->assertTrue($lookupField->isLookupField());
        $this->assertFalse($textField->isLookupField());
    }

    public function test_get_dependencies_from_conditional_visibility(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Field',
            'api_name' => 'field',
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

        $this->assertCount(2, $dependencies);
        $this->assertContains('status', $dependencies);
        $this->assertContains('type', $dependencies);
    }

    public function test_get_dependencies_from_formula(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Total',
            'api_name' => 'total',
            'type' => 'formula',
            'formula_definition' => [
                'formula' => 'amount * quantity',
                'formula_type' => 'calculation',
                'return_type' => 'number',
                'dependencies' => ['amount', 'quantity'],
                'recalculate_on' => ['amount', 'quantity'],
            ],
        ]);

        $dependencies = $field->getDependencies();

        $this->assertCount(2, $dependencies);
        $this->assertContains('amount', $dependencies);
        $this->assertContains('quantity', $dependencies);
    }

    public function test_get_dependencies_from_lookup(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Contact',
            'api_name' => 'contact_id',
            'type' => 'lookup',
            'lookup_settings' => [
                'related_module_id' => 1,
                'related_module_name' => 'contacts',
                'display_field' => 'full_name',
                'search_fields' => ['first_name', 'last_name'],
                'depends_on' => 'account_id',
                'dependency_filter' => [
                    'field' => 'account_id',
                    'operator' => 'equals',
                    'target_field' => 'account_id',
                ],
            ],
        ]);

        $dependencies = $field->getDependencies();

        $this->assertCount(1, $dependencies);
        $this->assertContains('account_id', $dependencies);
    }

    public function test_is_visible_evaluates_conditional_visibility(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Field',
            'api_name' => 'field',
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

    public function test_is_visible_returns_true_when_no_conditions(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Field',
            'api_name' => 'field',
            'type' => 'text',
        ]);

        $this->assertTrue($field->isVisible(['status' => 'anything']));
    }

    public function test_get_validation_rules_includes_base_rules(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Field',
            'api_name' => 'field',
            'type' => 'text',
            'is_required' => true,
            'validation_rules' => ['string', 'max:255'],
        ]);

        $rules = $field->getValidationRules();

        $this->assertContains('required', $rules);
        $this->assertContains('string', $rules);
        $this->assertContains('max:255', $rules);
    }

    public function test_get_validation_rules_adds_unique_rule(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Email',
            'api_name' => 'email',
            'type' => 'email',
            'is_unique' => true,
            'validation_rules' => ['email'],
        ]);

        $rules = $field->getValidationRules();

        $uniqueRule = array_filter($rules, fn ($rule) => str_starts_with($rule, 'unique:'));
        $this->assertCount(1, $uniqueRule);
    }

    public function test_get_validation_rules_does_not_duplicate_required(): void
    {
        $field = DB::table('fields')->insertGetId([
            'module_id' => $this->module->id,
            'label' => 'Field',
            'api_name' => 'field',
            'type' => 'text',
            'is_required' => true,
            'validation_rules' => ['required', 'string'],
        ]);

        $rules = $field->getValidationRules();

        $requiredCount = count(array_filter($rules, fn ($rule) => $rule === 'required'));
        $this->assertEquals(1, $requiredCount);
    }
}
