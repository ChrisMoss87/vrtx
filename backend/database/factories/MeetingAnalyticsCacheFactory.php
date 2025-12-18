<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MeetingAnalyticsCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MeetingAnalyticsCache>
 */
class MeetingAnalyticsCacheFactory extends Factory
{
    protected $model = MeetingAnalyticsCache::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalMeetings = $this->faker->numberBetween(5, 50);
        $avgDuration = $this->faker->numberBetween(30, 90);

        return [
            'entity_type' => $this->faker->randomElement([
                MeetingAnalyticsCache::ENTITY_DEAL,
                MeetingAnalyticsCache::ENTITY_ACCOUNT,
                MeetingAnalyticsCache::ENTITY_USER,
            ]),
            'entity_id' => $this->faker->numberBetween(1, 100),
            'period' => $this->faker->randomElement([
                MeetingAnalyticsCache::PERIOD_WEEK,
                MeetingAnalyticsCache::PERIOD_MONTH,
                MeetingAnalyticsCache::PERIOD_QUARTER,
            ]),
            'period_start' => now()->startOfMonth()->toDateString(),
            'total_meetings' => $totalMeetings,
            'total_duration_minutes' => $totalMeetings * $avgDuration,
            'unique_stakeholders' => $this->faker->numberBetween(2, 15),
            'meetings_per_week' => round($totalMeetings / 4, 2),
            'calculated_at' => now(),
        ];
    }

    /**
     * For a deal.
     */
    public function forDeal(int $dealId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => MeetingAnalyticsCache::ENTITY_DEAL,
            'entity_id' => $dealId ?? $this->faker->numberBetween(1, 100),
        ]);
    }

    /**
     * For an account.
     */
    public function forAccount(int $accountId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => MeetingAnalyticsCache::ENTITY_ACCOUNT,
            'entity_id' => $accountId ?? $this->faker->numberBetween(1, 100),
        ]);
    }

    /**
     * For a user.
     */
    public function forUser(int $userId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => MeetingAnalyticsCache::ENTITY_USER,
            'entity_id' => $userId ?? $this->faker->numberBetween(1, 100),
        ]);
    }

    /**
     * Weekly period.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period' => MeetingAnalyticsCache::PERIOD_WEEK,
            'period_start' => now()->startOfWeek()->toDateString(),
        ]);
    }

    /**
     * Monthly period.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period' => MeetingAnalyticsCache::PERIOD_MONTH,
            'period_start' => now()->startOfMonth()->toDateString(),
        ]);
    }

    /**
     * Quarterly period.
     */
    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'period' => MeetingAnalyticsCache::PERIOD_QUARTER,
            'period_start' => now()->firstOfQuarter()->toDateString(),
        ]);
    }

    /**
     * High engagement analytics.
     */
    public function highEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_meetings' => $this->faker->numberBetween(30, 60),
            'total_duration_minutes' => $this->faker->numberBetween(1500, 3000),
            'unique_stakeholders' => $this->faker->numberBetween(10, 20),
            'meetings_per_week' => $this->faker->randomFloat(2, 7, 15),
        ]);
    }

    /**
     * Low engagement analytics.
     */
    public function lowEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_meetings' => $this->faker->numberBetween(1, 5),
            'total_duration_minutes' => $this->faker->numberBetween(30, 150),
            'unique_stakeholders' => $this->faker->numberBetween(1, 3),
            'meetings_per_week' => $this->faker->randomFloat(2, 0.5, 1.5),
        ]);
    }
}
