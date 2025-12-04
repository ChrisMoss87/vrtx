<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\ValueObjects;

use App\Domain\Modules\ValueObjects\DependencyFilter;
use PHPUnit\Framework\TestCase;

class DependencyFilterTest extends TestCase
{
    public function test_from_array_creates_instance(): void
    {
        $data = [
            'field' => 'account_id',
            'operator' => 'equals',
            'target_field' => 'account_id',
        ];

        $filter = DependencyFilter::fromArray($data);

        $this->assertEquals('account_id', $filter->field);
        $this->assertEquals('equals', $filter->operator);
        $this->assertEquals('account_id', $filter->targetField);
    }

    public function test_from_array_with_static_value(): void
    {
        $data = [
            'field' => 'status',
            'operator' => 'equals',
            'target_field' => 'status',
            'static_value' => 'active',
        ];

        $filter = DependencyFilter::fromArray($data);

        $this->assertEquals('active', $filter->staticValue);
        $this->assertTrue($filter->hasStaticValue());
    }

    public function test_build_constraint_with_parent_value(): void
    {
        $filter = new DependencyFilter(
            field: 'account_id',
            operator: 'equals',
            targetField: 'account_id'
        );

        $constraint = $filter->buildConstraint(5);

        $this->assertEquals('account_id', $constraint['field']);
        $this->assertEquals('=', $constraint['operator']);
        $this->assertEquals(5, $constraint['value']);
    }

    public function test_build_constraint_with_static_value(): void
    {
        $filter = new DependencyFilter(
            field: 'status',
            operator: 'equals',
            targetField: 'status',
            staticValue: 'active'
        );

        $constraint = $filter->buildConstraint('ignored');

        $this->assertEquals('active', $constraint['value']);
    }

    public function test_build_where_clause_equals(): void
    {
        $filter = new DependencyFilter(
            field: 'account_id',
            operator: 'equals',
            targetField: 'account_id'
        );

        [$method, $params] = $filter->buildWhereClause(5);

        $this->assertEquals('where', $method);
        $this->assertEquals(['account_id', '=', 5], $params);
    }

    public function test_build_where_clause_not_equals(): void
    {
        $filter = new DependencyFilter(
            field: 'status',
            operator: 'not_equals',
            targetField: 'status'
        );

        [$method, $params] = $filter->buildWhereClause('inactive');

        $this->assertEquals('where', $method);
        $this->assertEquals(['status', '!=', 'inactive'], $params);
    }

    public function test_build_where_clause_greater_than(): void
    {
        $filter = new DependencyFilter(
            field: 'amount',
            operator: 'greater_than',
            targetField: 'min_amount'
        );

        [$method, $params] = $filter->buildWhereClause(1000);

        $this->assertEquals('where', $method);
        $this->assertEquals(['amount', '>', 1000], $params);
    }

    public function test_build_where_clause_in(): void
    {
        $filter = new DependencyFilter(
            field: 'category',
            operator: 'in',
            targetField: 'categories'
        );

        [$method, $params] = $filter->buildWhereClause(['tech', 'finance', 'retail']);

        $this->assertEquals('whereIn', $method);
        $this->assertEquals(['category', ['tech', 'finance', 'retail']], $params);
    }

    public function test_build_where_clause_not_in(): void
    {
        $filter = new DependencyFilter(
            field: 'category',
            operator: 'not_in',
            targetField: 'excluded_categories'
        );

        [$method, $params] = $filter->buildWhereClause(['spam', 'test']);

        $this->assertEquals('whereNotIn', $method);
        $this->assertEquals(['category', ['spam', 'test']], $params);
    }

    public function test_build_where_clause_contains(): void
    {
        $filter = new DependencyFilter(
            field: 'description',
            operator: 'contains',
            targetField: 'keyword'
        );

        [$method, $params] = $filter->buildWhereClause('urgent');

        $this->assertEquals('where', $method);
        $this->assertEquals(['description', 'like', '%urgent%'], $params);
    }

    public function test_invalid_operator_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid operator 'invalid'");

        new DependencyFilter(
            field: 'field',
            operator: 'invalid',
            targetField: 'target'
        );
    }

    public function test_valid_operators(): void
    {
        $validOperators = [
            'equals',
            'not_equals',
            'greater_than',
            'less_than',
            'greater_than_or_equal',
            'less_than_or_equal',
            'in',
            'not_in',
            'contains',
        ];

        foreach ($validOperators as $operator) {
            $filter = new DependencyFilter(
                field: 'test_field',
                operator: $operator,
                targetField: 'target_field'
            );

            $this->assertEquals($operator, $filter->operator);
        }
    }

    public function test_json_serialize(): void
    {
        $filter = new DependencyFilter(
            field: 'account_id',
            operator: 'equals',
            targetField: 'account_id'
        );

        $json = $filter->jsonSerialize();

        $this->assertArrayHasKey('field', $json);
        $this->assertArrayHasKey('operator', $json);
        $this->assertArrayHasKey('target_field', $json);
        $this->assertEquals('account_id', $json['field']);
        $this->assertEquals('equals', $json['operator']);
        $this->assertEquals('account_id', $json['target_field']);
    }

    public function test_json_serialize_with_static_value(): void
    {
        $filter = new DependencyFilter(
            field: 'status',
            operator: 'equals',
            targetField: 'status',
            staticValue: 'active'
        );

        $json = $filter->jsonSerialize();

        $this->assertArrayHasKey('static_value', $json);
        $this->assertEquals('active', $json['static_value']);
    }

    public function test_has_static_value_returns_false_without_value(): void
    {
        $filter = new DependencyFilter(
            field: 'account_id',
            operator: 'equals',
            targetField: 'account_id'
        );

        $this->assertFalse($filter->hasStaticValue());
    }

    public function test_has_static_value_returns_true_with_value(): void
    {
        $filter = new DependencyFilter(
            field: 'status',
            operator: 'equals',
            targetField: 'status',
            staticValue: 'active'
        );

        $this->assertTrue($filter->hasStaticValue());
    }
}
