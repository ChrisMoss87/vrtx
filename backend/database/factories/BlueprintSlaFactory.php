<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BlueprintSla>
 */
class BlueprintSlaFactory extends Factory
{
    protected $model = BlueprintSla::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blueprint_id' => Blueprint::factory(),
            'state_id' => BlueprintState::factory(),
            'name' => $this->faker->randomElement([
                'Qualification SLA',
                'Response Time SLA',
                'Resolution Time SLA',
                'First Response SLA',
                'Escalation SLA',
            ]),
            'duration_hours' => $this->faker->randomElement([4, 8, 24, 48, 72]),
            'business_hours_only' => $this->faker->boolean(60),
            'exclude_weekends' => $this->faker->boolean(70),
            'is_active' => true,
        ];
    }

    /**
     * Active SLA.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive SLA.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Business hours only.
     */
    public function businessHoursOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_hours_only' => true,
            'exclude_weekends' => true,
        ]);
    }

    /**
     * 24/7 SLA.
     */
    public function roundTheClock(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_hours_only' => false,
            'exclude_weekends' => false,
        ]);
    }
}
