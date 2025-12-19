<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuotaPeriod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuotaPeriod>
 */
class QuotaPeriodFactory extends Factory
{
    protected $model = QuotaPeriod::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([QuotaPeriod::TYPE_MONTH, QuotaPeriod::TYPE_QUARTER, QuotaPeriod::TYPE_YEAR]);
        $dates = $this->getDatesForType($type);

        return [
            'name' => $dates['name'],
            'period_type' => $type,
            'start_date' => $dates['start'],
            'end_date' => $dates['end'],
            'is_active' => true,
        ];
    }

    /**
     * Get dates based on period type.
     */
    private function getDatesForType(string $type): array
    {
        $now = Carbon::now();

        return match ($type) {
            QuotaPeriod::TYPE_MONTH => [
                'name' => $now->format('F Y'),
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            QuotaPeriod::TYPE_QUARTER => [
                'name' => 'Q' . $now->quarter . ' ' . $now->year,
                'start' => $now->copy()->startOfQuarter(),
                'end' => $now->copy()->endOfQuarter(),
            ],
            QuotaPeriod::TYPE_YEAR => [
                'name' => 'FY ' . $now->year,
                'start' => Carbon::create($now->year, 1, 1),
                'end' => Carbon::create($now->year, 12, 31),
            ],
            default => [
                'name' => 'Custom Period',
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->addMonths(3)->endOfMonth(),
            ],
        };
    }

    /**
     * Monthly period.
     */
    public function monthly(int $year = null, int $month = null): static
    {
        $date = Carbon::create($year ?? now()->year, $month ?? now()->month, 1);
        return $this->state(fn (array $attributes) => [
            'name' => $date->format('F Y'),
            'period_type' => QuotaPeriod::TYPE_MONTH,
            'start_date' => $date->copy()->startOfMonth(),
            'end_date' => $date->copy()->endOfMonth(),
        ]);
    }

    /**
     * Quarterly period.
     */
    public function quarterly(int $year = null, int $quarter = null): static
    {
        $year = $year ?? now()->year;
        $quarter = $quarter ?? now()->quarter;
        $quarterNames = ['Q1', 'Q2', 'Q3', 'Q4'];
        $startMonth = (($quarter - 1) * 3) + 1;
        $start = Carbon::create($year, $startMonth, 1);

        return $this->state(fn (array $attributes) => [
            'name' => $quarterNames[$quarter - 1] . ' ' . $year,
            'period_type' => QuotaPeriod::TYPE_QUARTER,
            'start_date' => $start,
            'end_date' => $start->copy()->addMonths(2)->endOfMonth(),
        ]);
    }

    /**
     * Yearly period.
     */
    public function yearly(int $year = null): static
    {
        $year = $year ?? now()->year;
        return $this->state(fn (array $attributes) => [
            'name' => "FY {$year}",
            'period_type' => QuotaPeriod::TYPE_YEAR,
            'start_date' => Carbon::create($year, 1, 1),
            'end_date' => Carbon::create($year, 12, 31),
        ]);
    }

    /**
     * Current period.
     */
    public function current(): static
    {
        $now = Carbon::now();
        return $this->state(fn (array $attributes) => [
            'start_date' => $now->copy()->startOfMonth(),
            'end_date' => $now->copy()->endOfMonth(),
            'is_active' => true,
        ]);
    }

    /**
     * Past period.
     */
    public function past(): static
    {
        $past = Carbon::now()->subMonths(3);
        return $this->state(fn (array $attributes) => [
            'start_date' => $past->copy()->startOfMonth(),
            'end_date' => $past->copy()->endOfMonth(),
        ]);
    }

    /**
     * Future period.
     */
    public function future(): static
    {
        $future = Carbon::now()->addMonths(3);
        return $this->state(fn (array $attributes) => [
            'start_date' => $future->copy()->startOfMonth(),
            'end_date' => $future->copy()->endOfMonth(),
        ]);
    }

    /**
     * Active period.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive period.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
