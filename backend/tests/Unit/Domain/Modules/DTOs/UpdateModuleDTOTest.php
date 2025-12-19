<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\DTOs;

use App\Domain\Modules\DTOs\UpdateModuleDTO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UpdateModuleDTOTest extends TestCase
{
    public function test_can_create_dto_with_id_only(): void
    {
        $dto = new UpdateModuleDTO(id: 1);

        $this->assertEquals(1, $dto->id);
        $this->assertNull($dto->name);
        $this->assertNull($dto->singularName);
        $this->assertNull($dto->apiName);
        $this->assertFalse($dto->hasUpdates());
    }

    public function test_can_create_dto_with_single_update(): void
    {
        $dto = new UpdateModuleDTO(id: 1, name: 'New Name');

        $this->assertEquals(1, $dto->id);
        $this->assertEquals('New Name', $dto->name);
        $this->assertTrue($dto->hasUpdates());
    }

    public function test_can_create_dto_with_multiple_updates(): void
    {
        $dto = new UpdateModuleDTO(
            id: 1,
            name: 'Updated Contacts',
            description: 'New description',
            isActive: false,
            displayOrder: 10
        );

        $this->assertEquals('Updated Contacts', $dto->name);
        $this->assertEquals('New description', $dto->description);
        $this->assertFalse($dto->isActive);
        $this->assertEquals(10, $dto->displayOrder);
    }

    public function test_from_array_creates_dto(): void
    {
        $dto = UpdateModuleDTO::fromArray(1, [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $this->assertEquals(1, $dto->id);
        $this->assertEquals('Updated Name', $dto->name);
        $this->assertEquals('Updated description', $dto->description);
    }

    public function test_from_array_supports_camel_case(): void
    {
        $dto = UpdateModuleDTO::fromArray(1, [
            'singularName' => 'Deal',
            'isActive' => false,
            'displayOrder' => 5,
        ]);

        $this->assertEquals('Deal', $dto->singularName);
        $this->assertFalse($dto->isActive);
        $this->assertEquals(5, $dto->displayOrder);
    }

    public function test_validation_fails_with_zero_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module ID must be positive');

        new UpdateModuleDTO(id: 0);
    }

    public function test_validation_fails_with_negative_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module ID must be positive');

        new UpdateModuleDTO(id: -1);
    }

    public function test_validation_fails_with_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module name cannot be empty');

        new UpdateModuleDTO(id: 1, name: '');
    }

    public function test_validation_fails_with_name_too_long(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module name cannot exceed 255 characters');

        new UpdateModuleDTO(id: 1, name: str_repeat('a', 256));
    }

    public function test_validation_fails_with_invalid_api_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Module API name must start with a letter');

        new UpdateModuleDTO(id: 1, apiName: 'Invalid-Name');
    }

    public function test_validation_fails_with_negative_display_order(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Display order cannot be negative');

        new UpdateModuleDTO(id: 1, displayOrder: -1);
    }

    public function test_to_array_only_includes_non_null_values(): void
    {
        $dto = new UpdateModuleDTO(
            id: 1,
            name: 'Updated',
            description: null,
            isActive: true
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayNotHasKey('description', $array);
        $this->assertArrayNotHasKey('id', $array);
    }

    public function test_has_updates_returns_true_with_updates(): void
    {
        $dto = new UpdateModuleDTO(id: 1, name: 'Updated');

        $this->assertTrue($dto->hasUpdates());
    }

    public function test_has_updates_returns_false_without_updates(): void
    {
        $dto = new UpdateModuleDTO(id: 1);

        $this->assertFalse($dto->hasUpdates());
    }

    public function test_get_updated_fields_returns_field_names(): void
    {
        $dto = new UpdateModuleDTO(
            id: 1,
            name: 'Updated',
            isActive: false
        );

        $fields = $dto->getUpdatedFields();

        $this->assertContains('name', $fields);
        $this->assertContains('is_active', $fields);
        $this->assertCount(2, $fields);
    }

    public function test_json_serialize_includes_id(): void
    {
        $dto = new UpdateModuleDTO(id: 1, name: 'Updated');

        $json = $dto->jsonSerialize();

        $this->assertArrayHasKey('id', $json);
        $this->assertEquals(1, $json['id']);
        $this->assertArrayHasKey('name', $json);
    }

    public function test_null_values_are_not_included_in_to_array(): void
    {
        $dto = new UpdateModuleDTO(
            id: 1,
            name: 'Test',
            icon: null,
            description: null
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('icon', $array);
        $this->assertArrayNotHasKey('description', $array);
    }

    public function test_can_update_settings(): void
    {
        $dto = new UpdateModuleDTO(
            id: 1,
            settings: ['new_setting' => 'value']
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('settings', $array);
        $this->assertEquals(['new_setting' => 'value'], $array['settings']);
    }

    public function test_can_update_is_active_to_false(): void
    {
        $dto = new UpdateModuleDTO(id: 1, isActive: false);

        $array = $dto->toArray();

        $this->assertArrayHasKey('is_active', $array);
        $this->assertFalse($array['is_active']);
    }

    public function test_can_update_display_order_to_zero(): void
    {
        $dto = new UpdateModuleDTO(id: 1, displayOrder: 0);

        $array = $dto->toArray();

        $this->assertArrayHasKey('display_order', $array);
        $this->assertEquals(0, $array['display_order']);
    }
}
