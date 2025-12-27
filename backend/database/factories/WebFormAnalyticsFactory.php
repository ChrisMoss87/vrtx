<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\WebForm\Entities\WebFormAnalytics;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\WebForm\Entities\WebFormAnalytics>
 */
class WebFormAnalyticsFactory extends Factory
{
    protected $model = WebFormAnalytics::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $views = $this->faker->numberBetween(50, 500);
        $submissions = (int) ($views * $this->faker->randomFloat(2, 0.05, 0.20));
        $successfulSubmissions = (int) ($submissions * $this->faker->randomFloat(2, 0.85, 0.98));
        $spamBlocked = $this->faker->numberBetween(0, 20);

        return [
            'web_form_id' => WebForm::factory(),
            'date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'views' => $views,
            'submissions' => $submissions,
            'successful_submissions' => $successfulSubmissions,
            'spam_blocked' => $spamBlocked,
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
     * Yesterday's analytics.
     */
    public function yesterday(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->subDay()->toDateString(),
        ]);
    }

    /**
     * High performing form analytics.
     */
    public function highPerforming(): static
    {
        $views = $this->faker->numberBetween(200, 500);
        $submissions = (int) ($views * 0.25);

        return $this->state(fn (array $attributes) => [
            'views' => $views,
            'submissions' => $submissions,
            'successful_submissions' => $submissions - 2,
            'spam_blocked' => 2,
        ]);
    }

    /**
     * Low performing form analytics.
     */
    public function lowPerforming(): static
    {
        $views = $this->faker->numberBetween(100, 300);
        $submissions = (int) ($views * 0.02);

        return $this->state(fn (array $attributes) => [
            'views' => $views,
            'submissions' => $submissions,
            'successful_submissions' => max(0, $submissions - 1),
            'spam_blocked' => 1,
        ]);
    }

    /**
     * With spam attacks.
     */
    public function withSpamAttack(): static
    {
        return $this->state(fn (array $attributes) => [
            'spam_blocked' => $this->faker->numberBetween(50, 200),
        ]);
    }
}
