<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\DTOs;

use App\Domain\Modules\DTOs\CreateFieldDTO;
use App\Domain\Modules\DTOs\CreateFieldOptionDTO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CreateFieldDTOTest extends TestCase
{
    public function test_can_create_dto_with_minimum_data(): void
    {
        $dto = new CreateFieldDTO(
            label: 'First Name',
            apiName: 'first_name',
            type: 'text'
        );

        $this->assertEquals('First Name', $dto->label);
        $this->assertEquals('first_name', $dto->apiName);
        $this->assertEquals('text', $dto->type);
        $this->assertFalse($dto->isRequired);
        $this->assertTrue($dto->isSearchable);
        $this->assertEquals(100, $dto->width);
    }

    public function test_can_create_dto_with_all_data(): void
    {
        $dto = new CreateFieldDTO(
            label: 'Email Address',
            apiName: 'email',
            type: 'email',
            blockApiName: 'contact_info',
            description: 'Primary email',
            helpText: 'Enter valid email',
            placeholder: 'user@example.com',
            isRequired: true,
            isUnique: true,
            isSearchable: true,
            isFilterable: true,
            isSortable: true,
            validationRules: ['email', 'max:255'],
            settings: ['max_length' => 255],
            defaultValue: null,
            displayOrder: 2,
            width: 50
        );

        $this->assertEquals('Email Address', $dto->label);
        $this->assertEquals('email', $dto->apiName);
        $this->assertTrue($dto->isRequired);
        $this->assertTrue($dto->isUnique);
        $this->assertEquals(50, $dto->width);
    }

    public function test_from_array_creates_dto(): void
    {
        $data = [
            'label' => 'Phone Number',
            'api_name' => 'phone',
            'type' => 'phone',
            'is_required' => true,
            'width' => 33,
        ];

        $dto = CreateFieldDTO::fromArray($data);

        $this->assertEquals('Phone Number', $dto->label);
        $this->assertEquals('phone', $dto->apiName);
        $this->assertEquals('phone', $dto->type);
        $this->assertTrue($dto->isRequired);
        $this->assertEquals(33, $dto->width);
    }

    public function test_from_array_supports_camel_case(): void
    {
        $data = [
            'label' => 'Name',
            'apiName' => 'name',
            'type' => 'text',
            'isRequired' => true,
            'isUnique' => false,
            'displayOrder' => 5,
        ];

        $dto = CreateFieldDTO::fromArray($data);

        $this->assertEquals('Name', $dto->label);
        $this->assertEquals('name', $dto->apiName);
        $this->assertTrue($dto->isRequired);
        $this->assertFalse($dto->isUnique);
        $this->assertEquals(5, $dto->displayOrder);
    }

    public function test_from_array_generates_api_name(): void
    {
        $data = [
            'label' => 'Company Name',
            'type' => 'text',
        ];

        $dto = CreateFieldDTO::fromArray($data);

        $this->assertEquals('company_name', $dto->apiName);
    }

    public function test_from_array_with_options(): void
    {
        $data = [
            'label' => 'Priority',
            'type' => 'select',
            'options' => [
                ['label' => 'High', 'value' => 'high'],
                ['label' => 'Low', 'value' => 'low'],
            ],
        ];

        $dto = CreateFieldDTO::fromArray($data);

        $this->assertCount(2, $dto->options);
        $this->assertInstanceOf(CreateFieldOptionDTO::class, $dto->options[0]);
        $this->assertEquals('High', $dto->options[0]->label);
    }

    public function test_validation_fails_with_empty_label(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field label is required');

        new CreateFieldDTO(
            label: '',
            apiName: 'test',
            type: 'text'
        );
    }

    public function test_validation_fails_with_label_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field label cannot exceed 255 characters');

        new CreateFieldDTO(
            label: str_repeat('a', 256),
            apiName: 'test',
            type: 'text'
        );
    }

    public function test_validation_fails_with_empty_api_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field API name is required');

        new CreateFieldDTO(
            label: 'Test',
            apiName: '',
            type: 'text'
        );
    }

    public function test_validation_fails_with_invalid_api_name_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field API name must start with a letter');

        new CreateFieldDTO(
            label: 'Test',
            apiName: '123field',
            type: 'text'
        );
    }

    public function test_validation_fails_with_invalid_field_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field type must be one of:');

        new CreateFieldDTO(
            label: 'Test',
            apiName: 'test',
            type: 'invalid_type'
        );
    }

    public function test_validation_fails_with_negative_display_order(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Display order cannot be negative');

        new CreateFieldDTO(
            label: 'Test',
            apiName: 'test',
            type: 'text',
            displayOrder: -1
        );
    }

    public function test_validation_fails_with_invalid_width(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field width must be between 1 and 100');

        new CreateFieldDTO(
            label: 'Test',
            apiName: 'test',
            type: 'text',
            width: 101
        );
    }

    public function test_validation_fails_for_select_without_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field type "select" requires at least one option');

        new CreateFieldDTO(
            label: 'Status',
            apiName: 'status',
            type: 'select',
            options: []
        );
    }

    public function test_validation_passes_for_text_without_options(): void
    {
        $dto = new CreateFieldDTO(
            label: 'Name',
            apiName: 'name',
            type: 'text',
            options: []
        );

        $this->assertEmpty($dto->options);
    }

    public function test_all_field_types_are_valid(): void
    {
        $types = [
            'text', 'email', 'phone', 'url', 'textarea', 'rich_text',
            'number', 'currency', 'percent', 'date', 'datetime', 'time',
            'checkbox', 'file', 'image', 'formula', 'auto_number', 'lookup'
        ];

        foreach ($types as $type) {
            $dto = new CreateFieldDTO(
                label: 'Test',
                apiName: "test_{$type}",
                type: $type
            );

            $this->assertEquals($type, $dto->type);
        }
    }

    public function test_has_options_returns_true_when_options_exist(): void
    {
        $dto = new CreateFieldDTO(
            label: 'Status',
            apiName: 'status',
            type: 'select',
            options: [
                new CreateFieldOptionDTO(label: 'Active', value: 'active'),
            ]
        );

        $this->assertTrue($dto->hasOptions());
    }

    public function test_has_options_returns_false_when_no_options(): void
    {
        $dto = new CreateFieldDTO(
            label: 'Name',
            apiName: 'name',
            type: 'text'
        );

        $this->assertFalse($dto->hasOptions());
    }

    public function test_get_options_data_returns_array(): void
    {
        $dto = new CreateFieldDTO(
            label: 'Status',
            apiName: 'status',
            type: 'select',
            options: [
                new CreateFieldOptionDTO(label: 'Active', value: 'active'),
            ]
        );

        $optionsData = $dto->getOptionsData();

        $this->assertIsArray($optionsData);
        $this->assertCount(1, $optionsData);
        $this->assertArrayHasKey('label', $optionsData[0]);
        $this->assertEquals('Active', $optionsData[0]['label']);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $dto = new CreateFieldDTO(
            label: 'Email',
            apiName: 'email',
            type: 'email',
            isRequired: true
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('label', $array);
        $this->assertArrayHasKey('api_name', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('is_required', $array);
        $this->assertArrayHasKey('validation_rules', $array);
    }

    public function test_json_serialize_includes_block_api_name(): void
    {
        $dto = new CreateFieldDTO(
            label: 'Email',
            apiName: 'email',
            type: 'email',
            blockApiName: 'contact_info'
        );

        $json = $dto->jsonSerialize();

        $this->assertArrayHasKey('block_api_name', $json);
        $this->assertEquals('contact_info', $json['block_api_name']);
    }

    public function test_conditional_visibility_is_validated(): void
    {
        $dto = new CreateFieldDTO(
            label: 'Test',
            apiName: 'test',
            type: 'text',
            conditionalVisibility: [
                'enabled' => true,
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
                ],
            ]
        );

        $this->assertNotNull($dto->conditionalVisibility);
    }

    public function test_lookup_settings_is_validated(): void
    {
        $dto = new CreateFieldDTO(
            label: 'Account',
            apiName: 'account_id',
            type: 'lookup',
            lookupSettings: [
                'target_module' => 'accounts',
                'relationship_type' => 'many_to_one',
                'display_field' => 'name',
            ]
        );

        $this->assertNotNull($dto->lookupSettings);
    }
}
