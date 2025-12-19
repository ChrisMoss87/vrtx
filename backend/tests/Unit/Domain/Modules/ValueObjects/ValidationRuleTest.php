<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\ValueObjects;

use App\Domain\Modules\ValueObjects\ValidationRule;
use PHPUnit\Framework\TestCase;

class ValidationRuleTest extends TestCase
{
    public function test_none_creates_empty_rule(): void
    {
        $rule = ValidationRule::none();

        $this->assertFalse($rule->hasRules());
        $this->assertEmpty($rule->toValidationArray());
        $this->assertEmpty($rule->getMessages());
    }

    public function test_from_array_creates_instance(): void
    {
        $data = [
            'rules' => ['required', 'string', 'max:255'],
            'messages' => ['required' => 'This field is required'],
            'custom_validation' => ['unique_check' => true],
        ];

        $rule = ValidationRule::fromArray($data);

        $this->assertTrue($rule->hasRules());
        $this->assertCount(3, $rule->toValidationArray());
        $this->assertCount(1, $rule->getMessages());
    }

    public function test_has_rule_checks_existence(): void
    {
        $rule = new ValidationRule(['required', 'string', 'max:255']);

        $this->assertTrue($rule->hasRule('required'));
        $this->assertTrue($rule->hasRule('string'));
        $this->assertTrue($rule->hasRule('max'));
        $this->assertFalse($rule->hasRule('min'));
    }

    public function test_is_required_checks_required_rule(): void
    {
        $requiredRule = new ValidationRule(['required', 'string']);
        $optionalRule = new ValidationRule(['string', 'max:255']);

        $this->assertTrue($requiredRule->isRequired());
        $this->assertFalse($optionalRule->isRequired());
    }

    public function test_is_unique_checks_unique_rule(): void
    {
        $uniqueRule = new ValidationRule(['required', 'unique:users,email']);
        $nonUniqueRule = new ValidationRule(['required', 'string']);

        $this->assertTrue($uniqueRule->isUnique());
        $this->assertFalse($nonUniqueRule->isUnique());
    }

    public function test_add_rules_combines_rules(): void
    {
        $rule = new ValidationRule(['required', 'string']);
        $newRule = $rule->addRules(['max:255', 'min:3']);

        $this->assertCount(4, $newRule->toValidationArray());
        $this->assertTrue($newRule->hasRule('max'));
        $this->assertTrue($newRule->hasRule('min'));
    }

    public function test_add_rules_prevents_duplicates(): void
    {
        $rule = new ValidationRule(['required', 'string']);
        $newRule = $rule->addRules(['required', 'max:255']);

        $array = $newRule->toValidationArray();
        $this->assertCount(3, $array); // required only appears once
    }

    public function test_remove_rule_removes_matching_rule(): void
    {
        $rule = new ValidationRule(['required', 'string', 'max:255']);
        $newRule = $rule->removeRule('max');

        $this->assertFalse($newRule->hasRule('max'));
        $this->assertTrue($newRule->hasRule('required'));
        $this->assertTrue($newRule->hasRule('string'));
    }

    public function test_merge_combines_two_rules(): void
    {
        $rule1 = new ValidationRule(
            ['required', 'string'],
            ['required' => 'Field is required']
        );
        $rule2 = new ValidationRule(
            ['max:255', 'min:3'],
            ['max' => 'Too long']
        );

        $merged = $rule1->merge($rule2);

        $this->assertCount(4, $merged->toValidationArray());
        $this->assertCount(2, $merged->getMessages());
    }

    public function test_get_required_rules_returns_required_variants(): void
    {
        $rule = new ValidationRule([
            'required',
            'required_if:status,active',
            'required_unless:type,optional',
            'string',
        ]);

        $requiredRules = $rule->getRequiredRules();

        $this->assertCount(3, $requiredRules);
        $this->assertContains('required', $requiredRules);
        $this->assertContains('required_if:status,active', $requiredRules);
        $this->assertContains('required_unless:type,optional', $requiredRules);
    }

    public function test_for_field_type_text(): void
    {
        $rule = ValidationRule::forFieldType('text', [
            'min_length' => 5,
            'max_length' => 100,
        ]);

        $this->assertTrue($rule->hasRule('string'));
        $this->assertTrue($rule->hasRule('min'));
        $this->assertTrue($rule->hasRule('max'));
    }

    public function test_for_field_type_email(): void
    {
        $rule = ValidationRule::forFieldType('email');

        $this->assertTrue($rule->hasRule('email'));
    }

    public function test_for_field_type_number(): void
    {
        $rule = ValidationRule::forFieldType('number', [
            'min_value' => 0,
            'max_value' => 100,
        ]);

        $this->assertTrue($rule->hasRule('integer'));
        $this->assertTrue($rule->hasRule('min'));
        $this->assertTrue($rule->hasRule('max'));
    }

    public function test_for_field_type_currency(): void
    {
        $rule = ValidationRule::forFieldType('currency', [
            'min_value' => 0,
            'max_value' => 999999.99,
            'precision' => 2,
        ]);

        $this->assertTrue($rule->hasRule('numeric'));
        $this->assertTrue($rule->hasRule('decimal'));
    }

    public function test_for_field_type_percent(): void
    {
        $rule = ValidationRule::forFieldType('percent');

        $this->assertTrue($rule->hasRule('numeric'));
        $this->assertTrue($rule->hasRule('min'));
        $this->assertTrue($rule->hasRule('max'));

        $rules = $rule->toValidationArray();
        $this->assertContains('min:0', $rules);
        $this->assertContains('max:100', $rules);
    }

    public function test_for_field_type_date(): void
    {
        $rule = ValidationRule::forFieldType('date', [
            'min_date' => 'today',
            'max_date' => '2025-12-31',
        ]);

        $this->assertTrue($rule->hasRule('date'));
        $this->assertTrue($rule->hasRule('after_or_equal'));
        $this->assertTrue($rule->hasRule('before_or_equal'));
    }

    public function test_for_field_type_file(): void
    {
        $rule = ValidationRule::forFieldType('file', [
            'max_file_size' => 10240, // 10MB in KB
            'allowed_file_types' => ['pdf', 'doc', 'docx'],
        ]);

        $this->assertTrue($rule->hasRule('file'));
        $this->assertTrue($rule->hasRule('max'));
        $this->assertTrue($rule->hasRule('mimes'));
    }

    public function test_for_field_type_image(): void
    {
        $rule = ValidationRule::forFieldType('image', [
            'max_file_size' => 5120,
            'max_width' => 1920,
            'max_height' => 1080,
        ]);

        $this->assertTrue($rule->hasRule('image'));
        $this->assertTrue($rule->hasRule('max'));
        $this->assertTrue($rule->hasRule('dimensions'));
    }

    public function test_for_field_type_multiselect(): void
    {
        $rule = ValidationRule::forFieldType('multiselect', [
            'max_selections' => 5,
        ]);

        $this->assertTrue($rule->hasRule('array'));
        $this->assertTrue($rule->hasRule('max'));
    }

    public function test_for_field_type_checkbox(): void
    {
        $rule = ValidationRule::forFieldType('checkbox');

        $this->assertTrue($rule->hasRule('boolean'));
    }

    public function test_for_field_type_url(): void
    {
        $rule = ValidationRule::forFieldType('url');

        $this->assertTrue($rule->hasRule('url'));
    }

    public function test_for_field_type_phone(): void
    {
        $rule = ValidationRule::forFieldType('phone');

        $this->assertTrue($rule->hasRule('string'));
    }

    public function test_for_field_type_lookup(): void
    {
        $rule = ValidationRule::forFieldType('lookup');

        $this->assertTrue($rule->hasRule('integer'));
    }

    public function test_json_serialize(): void
    {
        $rule = new ValidationRule(
            ['required', 'string', 'max:255'],
            ['required' => 'This field is required'],
            ['unique_check' => true]
        );

        $json = $rule->jsonSerialize();

        $this->assertArrayHasKey('rules', $json);
        $this->assertArrayHasKey('messages', $json);
        $this->assertArrayHasKey('custom_validation', $json);
        $this->assertCount(3, $json['rules']);
    }

    public function test_to_validation_array_returns_rules(): void
    {
        $rule = new ValidationRule(['required', 'string', 'max:255']);

        $array = $rule->toValidationArray();

        $this->assertIsArray($array);
        $this->assertCount(3, $array);
        $this->assertEquals(['required', 'string', 'max:255'], $array);
    }

    public function test_get_messages_returns_custom_messages(): void
    {
        $messages = [
            'required' => 'This field is required',
            'max' => 'Too long',
        ];

        $rule = new ValidationRule(['required', 'max:255'], $messages);

        $this->assertEquals($messages, $rule->getMessages());
    }
}
