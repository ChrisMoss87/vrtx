<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnalyticsAlert;
use App\Models\Module;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnalyticsAlert>
 */
class AnalyticsAlertFactory extends Factory
{
    protected $model = AnalyticsAlert::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'High Value Deal Alert',
                'Low Conversion Warning',
                'Pipeline Drop Alert',
                'Activity Threshold Exceeded',
                'Stale Deals Warning',
                'Win Rate Decline Alert',
            ]),
            'description' => $this->faker->sentence(),
            'user_id' => User::factory(),
            'alert_type' => $this->faker->randomElement([
                AnalyticsAlert::TYPE_THRESHOLD,
                AnalyticsAlert::TYPE_ANOMALY,
                AnalyticsAlert::TYPE_TREND,
            ]),
            'module_id' => fn () => Module::where('api_name', 'deals')->first()?->id ?? Module::first()?->id,
            'report_id' => null,
            'metric_field' => $this->faker->randomElement(['amount', 'count', 'conversion_rate']),
            'aggregation' => $this->faker->randomElement(['sum', 'count', 'avg']),
            'filters' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'open'],
            ],
            'condition_config' => [
                'operator' => '>',
                'threshold' => 100000,
            ],
            'notification_config' => [
                'channels' => ['in_app', 'email'],
                'recipients' => [],
                'frequency' => AnalyticsAlert::NOTIFY_IMMEDIATE,
            ],
            'check_frequency' => $this->faker->randomElement([
                AnalyticsAlert::FREQUENCY_HOURLY,
                AnalyticsAlert::FREQUENCY_DAILY,
            ]),
            'check_time' => '09:00',
            'is_active' => true,
            'last_checked_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 day', 'now'),
            'last_triggered_at' => $this->faker->optional(0.3)->dateTimeBetween('-7 days', 'now'),
            'trigger_count' => $this->faker->numberBetween(0, 20),
            'consecutive_triggers' => 0,
            'cooldown_minutes' => 60,
            'cooldown_until' => null,
        ];
    }

    /**
     * Threshold alert type.
     */
    public function threshold(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => AnalyticsAlert::TYPE_THRESHOLD,
            'name' => 'Threshold Alert',
            'condition_config' => [
                'operator' => '>',
                'threshold' => 100000,
            ],
        ]);
    }

    /**
     * Anomaly detection alert type.
     */
    public function anomaly(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => AnalyticsAlert::TYPE_ANOMALY,
            'name' => 'Anomaly Detection Alert',
            'condition_config' => [
                'sensitivity' => 'medium',
                'deviation_threshold' => 20,
            ],
        ]);
    }

    /**
     * Trend alert type.
     */
    public function trend(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => AnalyticsAlert::TYPE_TREND,
            'name' => 'Trend Alert',
            'condition_config' => [
                'direction' => 'decreasing',
                'periods' => 3,
                'min_change_percent' => 10,
            ],
        ]);
    }

    /**
     * Comparison alert type.
     */
    public function comparison(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => AnalyticsAlert::TYPE_COMPARISON,
            'name' => 'Period Comparison Alert',
            'condition_config' => [
                'compare_period' => 'previous_week',
                'change_threshold' => 15,
            ],
        ]);
    }

    /**
     * Active alert.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive alert.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Hourly check frequency.
     */
    public function hourly(): static
    {
        return $this->state(fn (array $attributes) => [
            'check_frequency' => AnalyticsAlert::FREQUENCY_HOURLY,
        ]);
    }

    /**
     * Daily check frequency.
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'check_frequency' => AnalyticsAlert::FREQUENCY_DAILY,
            'check_time' => '09:00',
        ]);
    }

    /**
     * Weekly check frequency.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'check_frequency' => AnalyticsAlert::FREQUENCY_WEEKLY,
            'check_time' => '09:00',
        ]);
    }

    /**
     * Recently triggered.
     */
    public function triggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_triggered_at' => now(),
            'trigger_count' => $this->faker->numberBetween(1, 20),
            'consecutive_triggers' => $this->faker->numberBetween(1, 5),
            'cooldown_until' => now()->addMinutes(60),
        ]);
    }

    /**
     * For a specific report.
     */
    public function forReport(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_id' => Report::factory(),
        ]);
    }
}
