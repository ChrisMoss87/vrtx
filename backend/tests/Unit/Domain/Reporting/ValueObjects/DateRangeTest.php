<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reporting\ValueObjects;

use App\Domain\Reporting\ValueObjects\DateRange;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    public function test_from_array_with_type(): void
    {
        $range = DateRange::fromArray([
            'field' => 'created_at',
            'type' => 'this_month',
        ]);

        $this->assertNotNull($range);
        $this->assertEquals('created_at', $range->field());
        $this->assertEquals('this_month', $range->type());
        $this->assertNull($range->start());
        $this->assertNull($range->end());
    }

    public function test_from_array_with_legacy_range_key(): void
    {
        $range = DateRange::fromArray([
            'field' => 'close_date',
            'range' => 'this_year',
        ]);

        $this->assertNotNull($range);
        $this->assertEquals('close_date', $range->field());
        $this->assertEquals('this_year', $range->type());
    }

    public function test_from_array_returns_null_for_empty_array(): void
    {
        $result = DateRange::fromArray([]);

        $this->assertNull($result);
    }

    public function test_from_array_returns_null_when_no_type_or_dates(): void
    {
        $result = DateRange::fromArray([
            'field' => 'created_at',
        ]);

        $this->assertNull($result);
    }

    public function test_from_array_with_custom_dates(): void
    {
        $range = DateRange::fromArray([
            'field' => 'due_date',
            'start' => '2024-01-01 00:00:00',
            'end' => '2024-12-31 23:59:59',
        ]);

        $this->assertNotNull($range);
        $this->assertEquals('due_date', $range->field());
        $this->assertNull($range->type());
        $this->assertNotNull($range->start());
        $this->assertNotNull($range->end());
        $this->assertEquals('2024-01-01', $range->start()->format('Y-m-d'));
        $this->assertEquals('2024-12-31', $range->end()->format('Y-m-d'));
    }

    public function test_from_type_factory(): void
    {
        $range = DateRange::fromType('created_at', 'last_30_days');

        $this->assertEquals('created_at', $range->field());
        $this->assertEquals('last_30_days', $range->type());
        $this->assertNull($range->start());
        $this->assertNull($range->end());
    }

    public function test_custom_factory(): void
    {
        $start = new DateTimeImmutable('2024-01-01');
        $end = new DateTimeImmutable('2024-12-31');

        $range = DateRange::custom('updated_at', $start, $end);

        $this->assertEquals('updated_at', $range->field());
        $this->assertNull($range->type());
        $this->assertEquals($start, $range->start());
        $this->assertEquals($end, $range->end());
    }

    public function test_get_calculated_dates_for_type(): void
    {
        $range = DateRange::fromType('created_at', 'today');
        $dates = $range->getCalculatedDates();

        $this->assertArrayHasKey('start', $dates);
        $this->assertArrayHasKey('end', $dates);
        $this->assertInstanceOf(DateTimeImmutable::class, $dates['start']);
        $this->assertInstanceOf(DateTimeImmutable::class, $dates['end']);

        // Both should be today
        $today = (new DateTimeImmutable())->format('Y-m-d');
        $this->assertEquals($today, $dates['start']->format('Y-m-d'));
        $this->assertEquals($today, $dates['end']->format('Y-m-d'));
    }

    public function test_get_calculated_dates_for_custom_range(): void
    {
        $start = new DateTimeImmutable('2024-01-01');
        $end = new DateTimeImmutable('2024-12-31');
        $range = DateRange::custom('created_at', $start, $end);

        $dates = $range->getCalculatedDates();

        $this->assertEquals($start, $dates['start']);
        $this->assertEquals($end, $dates['end']);
    }

    public function test_to_array(): void
    {
        $range = DateRange::fromType('created_at', 'this_month');
        $array = $range->toArray();

        $this->assertArrayHasKey('field', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('start', $array);
        $this->assertArrayHasKey('end', $array);
        $this->assertEquals('created_at', $array['field']);
        $this->assertEquals('this_month', $array['type']);
    }

    public function test_json_serialize(): void
    {
        $range = DateRange::fromType('created_at', 'last_7_days');
        $json = $range->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertEquals('created_at', $json['field']);
        $this->assertEquals('last_7_days', $json['type']);
    }

    public function test_all_predefined_types_calculate_correctly(): void
    {
        $types = [
            'today',
            'yesterday',
            'this_week',
            'last_week',
            'this_month',
            'last_month',
            'this_quarter',
            'last_quarter',
            'this_year',
            'last_year',
            'last_7_days',
            'last_30_days',
            'last_90_days',
        ];

        foreach ($types as $type) {
            $range = DateRange::fromType('created_at', $type);
            $dates = $range->getCalculatedDates();

            $this->assertArrayHasKey('start', $dates, "Type $type should have 'start' key");
            $this->assertArrayHasKey('end', $dates, "Type $type should have 'end' key");
            $this->assertInstanceOf(DateTimeImmutable::class, $dates['start'], "Type $type start should be DateTimeImmutable");
            $this->assertInstanceOf(DateTimeImmutable::class, $dates['end'], "Type $type end should be DateTimeImmutable");
            $this->assertLessThanOrEqual($dates['end'], $dates['start'], "Type $type start should be before or equal to end");
        }
    }

    public function test_default_field_when_not_specified(): void
    {
        $range = DateRange::fromArray([
            'type' => 'this_month',
        ]);

        $this->assertNotNull($range);
        $this->assertEquals('created_at', $range->field());
    }

    public function test_type_takes_precedence_over_range(): void
    {
        $range = DateRange::fromArray([
            'field' => 'created_at',
            'type' => 'this_month',
            'range' => 'this_year', // Should be ignored
        ]);

        $this->assertNotNull($range);
        $this->assertEquals('this_month', $range->type());
    }
}
