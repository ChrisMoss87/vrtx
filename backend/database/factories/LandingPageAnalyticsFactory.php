<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\LandingPage\Entities\LandingPageAnalytics;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\LandingPage\Entities\LandingPageAnalytics>
 */
class LandingPageAnalyticsFactory extends Factory
{
    protected $model = LandingPageAnalytics::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $views = $this->faker->numberBetween(100, 1000);
        $uniqueVisitors = (int) ($views * $this->faker->randomFloat(2, 0.6, 0.85));
        $submissions = (int) ($views * $this->faker->randomFloat(2, 0.02, 0.15));
        $bounces = (int) ($views * $this->faker->randomFloat(2, 0.3, 0.6));

        return [
            'page_id' => LandingPage::factory(),
            'variant_id' => null,
            'date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'views' => $views,
            'unique_visitors' => $uniqueVisitors,
            'form_submissions' => $submissions,
            'bounces' => $bounces,
            'avg_time_on_page' => $this->faker->numberBetween(30, 300),
            'referrer_breakdown' => [
                'google.com' => $this->faker->numberBetween(20, 100),
                'direct' => $this->faker->numberBetween(30, 150),
                'linkedin.com' => $this->faker->numberBetween(10, 50),
                'twitter.com' => $this->faker->numberBetween(5, 30),
            ],
            'device_breakdown' => [
                'desktop' => $this->faker->numberBetween(50, 70),
                'mobile' => $this->faker->numberBetween(25, 40),
                'tablet' => $this->faker->numberBetween(5, 15),
            ],
            'location_breakdown' => [
                'US' => $this->faker->numberBetween(40, 60),
                'UK' => $this->faker->numberBetween(10, 20),
                'DE' => $this->faker->numberBetween(5, 15),
                'CA' => $this->faker->numberBetween(5, 10),
            ],
        ];
    }

    /**
     * Today's analytics.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->toDateString(),
        ]);
    }

    /**
     * For a specific variant.
     */
    public function forVariant(): static
    {
        return $this->state(fn (array $attributes) => [
            'variant_id' => LandingPageVariant::factory(),
        ]);
    }

    /**
     * High performing page.
     */
    public function highPerforming(): static
    {
        $views = $this->faker->numberBetween(500, 1000);

        return $this->state(fn (array $attributes) => [
            'views' => $views,
            'unique_visitors' => (int) ($views * 0.8),
            'form_submissions' => (int) ($views * 0.15),
            'bounces' => (int) ($views * 0.25),
            'avg_time_on_page' => $this->faker->numberBetween(180, 300),
        ]);
    }

    /**
     * Low performing page.
     */
    public function lowPerforming(): static
    {
        $views = $this->faker->numberBetween(100, 300);

        return $this->state(fn (array $attributes) => [
            'views' => $views,
            'unique_visitors' => (int) ($views * 0.5),
            'form_submissions' => (int) ($views * 0.01),
            'bounces' => (int) ($views * 0.7),
            'avg_time_on_page' => $this->faker->numberBetween(10, 30),
        ]);
    }

    /**
     * Mobile heavy traffic.
     */
    public function mobileHeavy(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_breakdown' => [
                'desktop' => $this->faker->numberBetween(20, 30),
                'mobile' => $this->faker->numberBetween(60, 75),
                'tablet' => $this->faker->numberBetween(5, 10),
            ],
        ]);
    }
}
