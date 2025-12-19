<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\ValueObjects;

use App\Domain\Modules\ValueObjects\ConditionalVisibility;
use PHPUnit\Framework\TestCase;

class ConditionalVisibilityTest extends TestCase
{
    public function test_disabled_creates_inactive_visibility(): void
    {
        $visibility = ConditionalVisibility::disabled();

        $this->assertFalse($visibility->enabled);
        $this->assertFalse($visibility->isEnabled());
        $this->assertEmpty($visibility->conditions);
    }

    public function test_from_array_creates_instance(): void
    {
        $data = [
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                [
                    'field' => 'payment_terms',
                    'operator' => 'equals',
                    'value' => 'installments',
                ],
            ],
        ];

        $visibility = ConditionalVisibility::fromArray($data);

        $this->assertTrue($visibility->enabled);
        $this->assertEquals('and', $visibility->operator);
        $this->assertCount(1, $visibility->conditions);
    }

    public function test_from_empty_array_returns_disabled(): void
    {
        $visibility = ConditionalVisibility::fromArray([]);

        $this->assertFalse($visibility->enabled);
    }

    public function test_evaluate_returns_true_when_disabled(): void
    {
        $visibility = ConditionalVisibility::disabled();

        $result = $visibility->evaluate(['payment_terms' => 'installments']);

        $this->assertTrue($result);
    }

    public function test_evaluate_with_single_condition_and_operator(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                [
                    'field' => 'payment_terms',
                    'operator' => 'equals',
                    'value' => 'installments',
                ],
            ],
        ]);

        $this->assertTrue($visibility->evaluate(['payment_terms' => 'installments']));
        $this->assertFalse($visibility->evaluate(['payment_terms' => 'upfront']));
    }

    public function test_evaluate_with_multiple_conditions_and_operator(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                [
                    'field' => 'stage',
                    'operator' => 'equals',
                    'value' => 'negotiation',
                ],
                [
                    'field' => 'amount',
                    'operator' => 'greater_than',
                    'value' => 10000,
                ],
            ],
        ]);

        // Both conditions true
        $this->assertTrue($visibility->evaluate([
            'stage' => 'negotiation',
            'amount' => 15000,
        ]));

        // First condition false
        $this->assertFalse($visibility->evaluate([
            'stage' => 'prospecting',
            'amount' => 15000,
        ]));

        // Second condition false
        $this->assertFalse($visibility->evaluate([
            'stage' => 'negotiation',
            'amount' => 5000,
        ]));
    }

    public function test_evaluate_with_or_operator(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'or',
            'conditions' => [
                [
                    'field' => 'stage',
                    'operator' => 'equals',
                    'value' => 'proposal',
                ],
                [
                    'field' => 'amount',
                    'operator' => 'greater_than',
                    'value' => 50000,
                ],
            ],
        ]);

        // Both conditions true
        $this->assertTrue($visibility->evaluate([
            'stage' => 'proposal',
            'amount' => 60000,
        ]));

        // First condition true
        $this->assertTrue($visibility->evaluate([
            'stage' => 'proposal',
            'amount' => 10000,
        ]));

        // Second condition true
        $this->assertTrue($visibility->evaluate([
            'stage' => 'negotiation',
            'amount' => 60000,
        ]));

        // Both conditions false
        $this->assertFalse($visibility->evaluate([
            'stage' => 'prospecting',
            'amount' => 10000,
        ]));
    }

    public function test_get_dependencies_returns_field_names(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                ['field' => 'stage', 'operator' => 'equals', 'value' => 'negotiation'],
                ['field' => 'amount', 'operator' => 'greater_than', 'value' => 10000],
                ['field' => 'stage', 'operator' => 'not_equals', 'value' => 'lost'],
            ],
        ]);

        $dependencies = $visibility->getDependencies();

        $this->assertCount(2, $dependencies); // 'stage' and 'amount' (unique)
        $this->assertContains('stage', $dependencies);
        $this->assertContains('amount', $dependencies);
    }

    public function test_invalid_operator_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid operator 'invalid'");

        new ConditionalVisibility(
            enabled: true,
            operator: 'invalid',
            conditions: []
        );
    }

    public function test_json_serialize_when_disabled(): void
    {
        $visibility = ConditionalVisibility::disabled();

        $json = $visibility->jsonSerialize();

        $this->assertEquals(['enabled' => false], $json);
    }

    public function test_json_serialize_when_enabled(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                [
                    'field' => 'stage',
                    'operator' => 'equals',
                    'value' => 'negotiation',
                ],
            ],
        ]);

        $json = $visibility->jsonSerialize();

        $this->assertTrue($json['enabled']);
        $this->assertEquals('and', $json['operator']);
        $this->assertIsArray($json['conditions']);
        $this->assertCount(1, $json['conditions']);
    }

    public function test_condition_equals_operator(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ],
        ]);

        $this->assertTrue($visibility->evaluate(['status' => 'active']));
        $this->assertFalse($visibility->evaluate(['status' => 'inactive']));
    }

    public function test_condition_contains_operator(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                ['field' => 'description', 'operator' => 'contains', 'value' => 'urgent'],
            ],
        ]);

        $this->assertTrue($visibility->evaluate(['description' => 'This is an urgent matter']));
        $this->assertFalse($visibility->evaluate(['description' => 'Regular issue']));
    }

    public function test_condition_in_operator(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                ['field' => 'stage', 'operator' => 'in', 'value' => ['proposal', 'negotiation']],
            ],
        ]);

        $this->assertTrue($visibility->evaluate(['stage' => 'proposal']));
        $this->assertTrue($visibility->evaluate(['stage' => 'negotiation']));
        $this->assertFalse($visibility->evaluate(['stage' => 'prospecting']));
    }

    public function test_condition_is_empty_operator(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                ['field' => 'notes', 'operator' => 'is_empty'],
            ],
        ]);

        $this->assertTrue($visibility->evaluate(['notes' => '']));
        $this->assertTrue($visibility->evaluate(['notes' => null]));
        $this->assertTrue($visibility->evaluate([]));
        $this->assertFalse($visibility->evaluate(['notes' => 'Some text']));
    }

    public function test_condition_is_checked_operator(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                ['field' => 'agreed_to_terms', 'operator' => 'is_checked'],
            ],
        ]);

        $this->assertTrue($visibility->evaluate(['agreed_to_terms' => true]));
        $this->assertTrue($visibility->evaluate(['agreed_to_terms' => 1]));
        $this->assertTrue($visibility->evaluate(['agreed_to_terms' => '1']));
        $this->assertFalse($visibility->evaluate(['agreed_to_terms' => false]));
        $this->assertFalse($visibility->evaluate(['agreed_to_terms' => 0]));
    }

    public function test_condition_between_operator(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                ['field' => 'amount', 'operator' => 'between', 'value' => ['min' => 1000, 'max' => 5000]],
            ],
        ]);

        $this->assertTrue($visibility->evaluate(['amount' => 3000]));
        $this->assertTrue($visibility->evaluate(['amount' => 1000]));
        $this->assertTrue($visibility->evaluate(['amount' => 5000]));
        $this->assertFalse($visibility->evaluate(['amount' => 500]));
        $this->assertFalse($visibility->evaluate(['amount' => 6000]));
    }

    public function test_condition_field_comparison(): void
    {
        $visibility = ConditionalVisibility::fromArray([
            'enabled' => true,
            'operator' => 'and',
            'conditions' => [
                [
                    'field' => 'end_value',
                    'operator' => 'greater_than',
                    'field_value' => 'start_value',
                ],
            ],
        ]);

        $this->assertTrue($visibility->evaluate([
            'start_value' => 100,
            'end_value' => 200,
        ]));

        $this->assertFalse($visibility->evaluate([
            'start_value' => 200,
            'end_value' => 100,
        ]));
    }
}
