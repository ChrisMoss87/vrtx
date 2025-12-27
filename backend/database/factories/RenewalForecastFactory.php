<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contract\Entities\RenewalForecast;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Contract\Entities\RenewalForecast>
 */
class RenewalForecastFactory extends Factory
{
    protected $model = RenewalForecast::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalContracts = $this->faker->numberBetween(10, 50);
        $renewedCount = $this->faker->numberBetween(5, $totalContracts);
        $churnedCount = $this->faker->numberBetween(0, $totalContracts - $renewedCount);
        $atRiskCount = $totalContracts - $renewedCount - $churnedCount;

        $expectedRenewals = $this->faker->randomFloat(2, 50000, 500000);
        $renewedValue = $expectedRenewals * ($renewedCount / $totalContracts);
        $churnedValue = $expectedRenewals * ($churnedCount / $totalContracts);
        $atRiskValue = $expectedRenewals * ($atRiskCount / $totalContracts);
        $expansionValue = $renewedValue * $this->faker->randomFloat(2, 0.05, 0.20);

        return [
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'period_type' => 'month',
            'expected_renewals' => $expectedRenewals,
            'at_risk_value' => $atRiskValue,
            'churned_value' => $churnedValue,
            'renewed_value' => $renewedValue,
            'expansion_value' => $expansionValue,
            'total_contracts' => $totalContracts,
            'at_risk_count' => $atRiskCount,
            'renewed_count' => $renewedCount,
            'churned_count' => $churnedCount,
            'retention_rate' => ($renewedCount / $totalContracts) * 100,
        ];
    }

    /**
     * Monthly period.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => 'month',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);
    }

    /**
     * Quarterly period.
     */
    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => 'quarter',
            'period_start' => now()->startOfQuarter(),
            'period_end' => now()->endOfQuarter(),
        ]);
    }

    /**
     * Yearly period.
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => 'year',
            'period_start' => now()->startOfYear(),
            'period_end' => now()->endOfYear(),
        ]);
    }

    /**
     * High retention scenario.
     */
    public function highRetention(): static
    {
        return $this->state(function (array $attributes) {
            $totalContracts = $attributes['total_contracts'] ?? 30;
            $renewedCount = (int) ($totalContracts * 0.9);
            $churnedCount = (int) ($totalContracts * 0.05);
            $atRiskCount = $totalContracts - $renewedCount - $churnedCount;

            $expectedRenewals = $attributes['expected_renewals'] ?? 300000;
            $renewedValue = $expectedRenewals * 0.9;
            $churnedValue = $expectedRenewals * 0.05;
            $atRiskValue = $expectedRenewals * 0.05;
            $expansionValue = $renewedValue * 0.15;

            return [
                'renewed_count' => $renewedCount,
                'churned_count' => $churnedCount,
                'at_risk_count' => $atRiskCount,
                'renewed_value' => $renewedValue,
                'churned_value' => $churnedValue,
                'at_risk_value' => $atRiskValue,
                'expansion_value' => $expansionValue,
                'retention_rate' => 90.0,
            ];
        });
    }

    /**
     * At risk scenario.
     */
    public function atRisk(): static
    {
        return $this->state(function (array $attributes) {
            $totalContracts = $attributes['total_contracts'] ?? 30;
            $renewedCount = (int) ($totalContracts * 0.5);
            $atRiskCount = (int) ($totalContracts * 0.35);
            $churnedCount = $totalContracts - $renewedCount - $atRiskCount;

            $expectedRenewals = $attributes['expected_renewals'] ?? 300000;
            $renewedValue = $expectedRenewals * 0.5;
            $atRiskValue = $expectedRenewals * 0.35;
            $churnedValue = $expectedRenewals * 0.15;

            return [
                'renewed_count' => $renewedCount,
                'churned_count' => $churnedCount,
                'at_risk_count' => $atRiskCount,
                'renewed_value' => $renewedValue,
                'churned_value' => $churnedValue,
                'at_risk_value' => $atRiskValue,
                'expansion_value' => 0,
                'retention_rate' => 50.0,
            ];
        });
    }

    /**
     * Previous period (historical).
     */
    public function previous(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
        ]);
    }
}
