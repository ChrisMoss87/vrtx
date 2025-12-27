<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Entities\AnalyticsAlertHistory;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Analytics\Entities\AnalyticsAlertHistory>
 */
class AnalyticsAlertHistoryFactory extends Factory
{
    protected $model = AnalyticsAlertHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $metricValue = $this->faker->randomFloat(2, 50000, 500000);
        $thresholdValue = $this->faker->randomFloat(2, 100000, 200000);

        return [
            'alert_id' => AnalyticsAlert::factory(),
            'status' => $this->faker->randomElement([
                AnalyticsAlertHistory::STATUS_TRIGGERED,
                AnalyticsAlertHistory::STATUS_RESOLVED,
                AnalyticsAlertHistory::STATUS_ACKNOWLEDGED,
            ]),
            'metric_value' => $metricValue,
            'threshold_value' => $thresholdValue,
            'baseline_value' => $this->faker->randomFloat(2, 80000, 150000),
            'deviation_percent' => $this->faker->randomFloat(2, 10, 50),
            'context' => [
                'period' => 'last_7_days',
                'records_analyzed' => $this->faker->numberBetween(50, 500),
            ],
            'message' => null,
            'acknowledged_by' => null,
            'acknowledged_at' => null,
            'acknowledgment_note' => null,
            'notifications_sent' => [
                ['channel' => 'email', 'sent_at' => now()->subMinutes(5)->toIso8601String()],
                ['channel' => 'in_app', 'sent_at' => now()->subMinutes(5)->toIso8601String()],
            ],
        ];
    }

    /**
     * Triggered status.
     */
    public function triggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalyticsAlertHistory::STATUS_TRIGGERED,
            'acknowledged_by' => null,
            'acknowledged_at' => null,
        ]);
    }

    /**
     * Resolved status.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalyticsAlertHistory::STATUS_RESOLVED,
        ]);
    }

    /**
     * Acknowledged status.
     */
    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalyticsAlertHistory::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => User::factory(),
            'acknowledged_at' => now(),
            'acknowledgment_note' => $this->faker->randomElement([
                'Investigating the issue',
                'Expected spike due to promotion',
                'Will follow up with sales team',
            ]),
        ]);
    }

    /**
     * Muted status.
     */
    public function muted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalyticsAlertHistory::STATUS_MUTED,
        ]);
    }

    /**
     * High severity (large deviation).
     */
    public function highSeverity(): static
    {
        return $this->state(fn (array $attributes) => [
            'deviation_percent' => $this->faker->randomFloat(2, 50, 100),
            'message' => 'Critical: Significant deviation detected!',
        ]);
    }

    /**
     * With custom message.
     */
    public function withMessage(string $message): static
    {
        return $this->state(fn (array $attributes) => [
            'message' => $message,
        ]);
    }
}
