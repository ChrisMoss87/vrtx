<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\DTOs;

use App\Domain\Modules\DTOs\CreateBlockDTO;
use App\Domain\Modules\DTOs\CreateFieldDTO;
use App\Domain\Modules\DTOs\CreateModuleDTO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CreateModuleDTOTest extends TestCase
{
    public function test_can_create_dto_with_minimum_data(): void
    {
        $dto = new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts'
        );

        $this->assertEquals('Contacts', $dto->name);
        $this->assertEquals('Contact', $dto->singularName);
        $this->assertEquals('contacts', $dto->apiName);
        $this->assertTrue($dto->isActive);
        $this->assertEquals(0, $dto->displayOrder);
    }

    public function test_can_create_dto_with_all_data(): void
    {
        $dto = new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts',
            icon: 'users',
            description: 'Manage contacts',
            isActive: true,
            settings: ['color' => 'blue'],
            displayOrder: 5
        );

        $this->assertEquals('Contacts', $dto->name);
        $this->assertEquals('Contact', $dto->singularName);
        $this->assertEquals('contacts', $dto->apiName);
        $this->assertEquals('users', $dto->icon);
        $this->assertEquals('Manage contacts', $dto->description);
        $this->assertTrue($dto->isActive);
        $this->assertEquals(['color' => 'blue'], $dto->settings);
        $this->assertEquals(5, $dto->displayOrder);
    }

    public function test_from_array_creates_dto(): void
    {
        $data = [
            'name' => 'Deals',
            'singular_name' => 'Deal',
            'api_name' => 'deals',
            'icon' => 'briefcase',
            'description' => 'Sales deals',
            'is_active' => true,
            'settings' => ['stage' => 'pipeline'],
            'display_order' => 2,
        ];

        $dto = CreateModuleDTO::fromArray($data);

        $this->assertEquals('Deals', $dto->name);
        $this->assertEquals('Deal', $dto->singularName);
        $this->assertEquals('deals', $dto->apiName);
        $this->assertEquals('briefcase', $dto->icon);
    }

    public function test_from_array_supports_camel_case(): void
    {
        $data = [
            'name' => 'Accounts',
            'singularName' => 'Account',
            'apiName' => 'accounts',
            'isActive' => false,
            'displayOrder' => 3,
        ];

        $dto = CreateModuleDTO::fromArray($data);

        $this->assertEquals('Accounts', $dto->name);
        $this->assertEquals('Account', $dto->singularName);
        $this->assertEquals('accounts', $dto->apiName);
        $this->assertFalse($dto->isActive);
        $this->assertEquals(3, $dto->displayOrder);
    }

    public function test_from_array_generates_api_name_from_name(): void
    {
        $data = ['name' => 'Sales Orders'];

        $dto = CreateModuleDTO::fromArray($data);

        $this->assertEquals('sales_orders', $dto->apiName);
        $this->assertEquals('Sales Orders', $dto->singularName);
    }

    public function test_from_array_generates_singular_name_from_name(): void
    {
        $data = [
            'name' => 'Products',
            'api_name' => 'products',
        ];

        $dto = CreateModuleDTO::fromArray($data);

        $this->assertEquals('Products', $dto->singularName);
    }

    public function test_from_array_with_blocks(): void
    {
        $data = [
            'name' => 'Contacts',
            'blocks' => [
                ['name' => 'Basic Info', 'type' => 'section'],
                ['name' => 'Address', 'type' => 'section'],
            ],
        ];

        $dto = CreateModuleDTO::fromArray($data);

        $this->assertCount(2, $dto->blocks);
        $this->assertInstanceOf(CreateBlockDTO::class, $dto->blocks[0]);
        $this->assertEquals('Basic Info', $dto->blocks[0]->name);
    }

    public function test_from_array_with_fields(): void
    {
        $data = [
            'name' => 'Contacts',
            'fields' => [
                ['label' => 'First Name', 'type' => 'text'],
                ['label' => 'Email', 'type' => 'email'],
            ],
        ];

        $dto = CreateModuleDTO::fromArray($data);

        $this->assertCount(2, $dto->fields);
        $this->assertInstanceOf(CreateFieldDTO::class, $dto->fields[0]);
        $this->assertEquals('First Name', $dto->fields[0]->label);
    }

    public function test_validation_fails_with_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module name is required');

        new CreateModuleDTO(
            name: '',
            singularName: 'Contact',
            apiName: 'contacts'
        );
    }

    public function test_validation_fails_with_name_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module name cannot exceed 255 characters');

        new CreateModuleDTO(
            name: str_repeat('a', 256),
            singularName: 'Contact',
            apiName: 'contacts'
        );
    }

    public function test_validation_fails_with_empty_singular_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module singular name is required');

        new CreateModuleDTO(
            name: 'Contacts',
            singularName: '',
            apiName: 'contacts'
        );
    }

    public function test_validation_fails_with_empty_api_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module API name is required');

        new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: ''
        );
    }

    public function test_validation_fails_with_invalid_api_name_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module API name must start with a letter');

        new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: '123contacts'
        );
    }

    public function test_validation_fails_with_api_name_uppercase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module API name must start with a letter');

        new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'Contacts'
        );
    }

    public function test_validation_fails_with_api_name_special_chars(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts-module'
        );
    }

    public function test_validation_fails_with_negative_display_order(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Display order cannot be negative');

        new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts',
            displayOrder: -1
        );
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $dto = new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts',
            icon: 'users',
            description: 'Manage contacts',
            isActive: true,
            settings: ['color' => 'blue'],
            displayOrder: 5
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('singular_name', $array);
        $this->assertArrayHasKey('api_name', $array);
        $this->assertArrayHasKey('icon', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('settings', $array);
        $this->assertArrayHasKey('display_order', $array);
    }

    public function test_has_blocks_returns_true_when_blocks_exist(): void
    {
        $dto = new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts',
            blocks: [
                new CreateBlockDTO(name: 'Basic Info'),
            ]
        );

        $this->assertTrue($dto->hasBlocks());
    }

    public function test_has_blocks_returns_false_when_no_blocks(): void
    {
        $dto = new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts'
        );

        $this->assertFalse($dto->hasBlocks());
    }

    public function test_has_fields_returns_true_when_fields_exist(): void
    {
        $dto = new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts',
            fields: [
                new CreateFieldDTO(label: 'Name', apiName: 'name', type: 'text'),
            ]
        );

        $this->assertTrue($dto->hasFields());
    }

    public function test_json_serialize_returns_correct_structure(): void
    {
        $dto = new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts',
            icon: 'users'
        );

        $json = $dto->jsonSerialize();

        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('singular_name', $json);
        $this->assertArrayHasKey('api_name', $json);
        $this->assertArrayHasKey('icon', $json);
        $this->assertArrayHasKey('blocks', $json);
        $this->assertArrayHasKey('fields', $json);
    }

    public function test_get_blocks_data_returns_array(): void
    {
        $dto = new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts',
            blocks: [
                new CreateBlockDTO(name: 'Basic Info'),
            ]
        );

        $blocksData = $dto->getBlocksData();

        $this->assertIsArray($blocksData);
        $this->assertCount(1, $blocksData);
        $this->assertArrayHasKey('name', $blocksData[0]);
    }

    public function test_get_fields_data_returns_array(): void
    {
        $dto = new CreateModuleDTO(
            name: 'Contacts',
            singularName: 'Contact',
            apiName: 'contacts',
            fields: [
                new CreateFieldDTO(label: 'Name', apiName: 'name', type: 'text'),
            ]
        );

        $fieldsData = $dto->getFieldsData();

        $this->assertIsArray($fieldsData);
        $this->assertCount(1, $fieldsData);
        $this->assertArrayHasKey('label', $fieldsData[0]);
    }
}
