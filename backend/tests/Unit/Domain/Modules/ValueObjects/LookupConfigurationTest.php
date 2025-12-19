<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\ValueObjects;

use App\Domain\Modules\ValueObjects\DependencyFilter;
use App\Domain\Modules\ValueObjects\LookupConfiguration;
use PHPUnit\Framework\TestCase;

class LookupConfigurationTest extends TestCase
{
    public function test_from_array_creates_instance(): void
    {
        $data = [
            'related_module_id' => 1,
            'related_module_name' => 'contacts',
            'display_field' => 'full_name',
            'search_fields' => ['first_name', 'last_name', 'email'],
            'allow_create' => true,
            'cascade_delete' => false,
            'relationship_type' => 'many_to_one',
        ];

        $config = LookupConfiguration::fromArray($data);

        $this->assertEquals(1, $config->relatedModuleId);
        $this->assertEquals('contacts', $config->relatedModuleName);
        $this->assertEquals('full_name', $config->displayField);
        $this->assertCount(3, $config->searchFields);
        $this->assertTrue($config->allowCreate);
        $this->assertFalse($config->cascadeDelete);
        $this->assertEquals('many_to_one', $config->relationshipType);
    }

    public function test_from_array_with_defaults(): void
    {
        $data = [
            'related_module_id' => 1,
            'related_module_name' => 'contacts',
        ];

        $config = LookupConfiguration::fromArray($data);

        $this->assertEquals('name', $config->displayField);
        $this->assertEmpty($config->searchFields);
        $this->assertFalse($config->allowCreate);
        $this->assertFalse($config->cascadeDelete);
        $this->assertEquals('many_to_one', $config->relationshipType);
    }

    public function test_has_dependency_returns_false_without_dependency(): void
    {
        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
        );

        $this->assertFalse($config->hasDependency());
    }

    public function test_has_dependency_returns_true_with_dependency(): void
    {
        $filter = new DependencyFilter(
            field: 'account_id',
            operator: 'equals',
            targetField: 'account_id'
        );

        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
            dependsOn: 'account_id',
            dependencyFilter: $filter,
        );

        $this->assertTrue($config->hasDependency());
    }

    public function test_get_quick_create_fields(): void
    {
        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
            additionalSettings: [
                'quick_create_fields' => ['first_name', 'last_name', 'email'],
            ],
        );

        $fields = $config->getQuickCreateFields();

        $this->assertCount(3, $fields);
        $this->assertEquals(['first_name', 'last_name', 'email'], $fields);
    }

    public function test_should_show_recent(): void
    {
        $configWithRecent = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
            additionalSettings: ['show_recent' => true],
        );

        $configWithoutRecent = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
        );

        $this->assertTrue($configWithRecent->shouldShowRecent());
        $this->assertFalse($configWithoutRecent->shouldShowRecent());
    }

    public function test_get_recent_limit(): void
    {
        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
            additionalSettings: ['recent_limit' => 20],
        );

        $this->assertEquals(20, $config->getRecentLimit());
    }

    public function test_get_recent_limit_default(): void
    {
        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
        );

        $this->assertEquals(10, $config->getRecentLimit());
    }

    public function test_get_static_filters(): void
    {
        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
            additionalSettings: [
                'filters' => [
                    ['field' => 'is_active', 'operator' => '=', 'value' => true],
                    ['field' => 'type', 'operator' => '=', 'value' => 'customer'],
                ],
            ],
        );

        $filters = $config->getStaticFilters();

        $this->assertCount(2, $filters);
        $this->assertEquals('is_active', $filters[0]['field']);
    }

    public function test_build_query_constraints_with_static_filters(): void
    {
        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
            additionalSettings: [
                'filters' => [
                    ['field' => 'is_active', 'operator' => '=', 'value' => true],
                ],
            ],
        );

        $constraints = $config->buildQueryConstraints();

        $this->assertCount(1, $constraints);
        $this->assertEquals('is_active', $constraints[0]['field']);
        $this->assertEquals('=', $constraints[0]['operator']);
        $this->assertTrue($constraints[0]['value']);
    }

    public function test_build_query_constraints_with_dependency(): void
    {
        $filter = new DependencyFilter(
            field: 'account_id',
            operator: 'equals',
            targetField: 'account_id'
        );

        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
            dependsOn: 'account_id',
            dependencyFilter: $filter,
        );

        $constraints = $config->buildQueryConstraints(['account_id' => 5]);

        $this->assertCount(1, $constraints);
        $this->assertEquals('account_id', $constraints[0]['field']);
        $this->assertEquals(5, $constraints[0]['value']);
    }

    public function test_build_query_constraints_without_dependency_value(): void
    {
        $filter = new DependencyFilter(
            field: 'account_id',
            operator: 'equals',
            targetField: 'account_id'
        );

        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
            dependsOn: 'account_id',
            dependencyFilter: $filter,
        );

        $constraints = $config->buildQueryConstraints(['other_field' => 'value']);

        $this->assertEmpty($constraints); // No dependency value, no constraint added
    }

    public function test_invalid_relationship_type_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid relationship type 'invalid'");

        new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
            relationshipType: 'invalid',
        );
    }

    public function test_valid_relationship_types(): void
    {
        $types = ['one_to_one', 'many_to_one', 'many_to_many'];

        foreach ($types as $type) {
            $config = new LookupConfiguration(
                relatedModuleId: 1,
                relatedModuleName: 'contacts',
                displayField: 'full_name',
                searchFields: ['full_name'],
                relationshipType: $type,
            );

            $this->assertEquals($type, $config->relationshipType);
        }
    }

    public function test_json_serialize(): void
    {
        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['first_name', 'last_name'],
            allowCreate: true,
            cascadeDelete: false,
            relationshipType: 'many_to_one',
            additionalSettings: ['show_recent' => true],
        );

        $json = $config->jsonSerialize();

        $this->assertArrayHasKey('related_module_id', $json);
        $this->assertArrayHasKey('related_module_name', $json);
        $this->assertArrayHasKey('display_field', $json);
        $this->assertArrayHasKey('search_fields', $json);
        $this->assertArrayHasKey('allow_create', $json);
        $this->assertArrayHasKey('cascade_delete', $json);
        $this->assertArrayHasKey('relationship_type', $json);
        $this->assertArrayHasKey('additional_settings', $json);
    }

    public function test_json_serialize_with_dependency(): void
    {
        $filter = new DependencyFilter(
            field: 'account_id',
            operator: 'equals',
            targetField: 'account_id'
        );

        $config = new LookupConfiguration(
            relatedModuleId: 1,
            relatedModuleName: 'contacts',
            displayField: 'full_name',
            searchFields: ['full_name'],
            dependsOn: 'account_id',
            dependencyFilter: $filter,
        );

        $json = $config->jsonSerialize();

        $this->assertArrayHasKey('depends_on', $json);
        $this->assertArrayHasKey('dependency_filter', $json);
        $this->assertEquals('account_id', $json['depends_on']);
    }
}
