<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\LandingPage\Entities\LandingPageVariant;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\LandingPage\Entities\LandingPageVariant>
 */
class LandingPageVariantFactory extends Factory
{
    protected $model = LandingPageVariant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => LandingPage::factory(),
            'name' => 'Variant ' . $this->faker->randomElement(['A', 'B', 'C', 'D']),
            'variant_code' => strtolower($this->faker->randomElement(['a', 'b', 'c', 'd'])),
            'content' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'headline' => $this->faker->sentence(5),
                        'subheadline' => $this->faker->sentence(10),
                        'cta_text' => $this->faker->randomElement(['Get Started', 'Sign Up Now', 'Learn More', 'Try Free']),
                    ],
                ],
            ],
            'styles' => [
                'primary_color' => $this->faker->hexColor(),
                'button_style' => $this->faker->randomElement(['rounded', 'square', 'pill']),
            ],
            'traffic_percentage' => 50,
            'is_active' => true,
            'is_winner' => false,
            'declared_winner_at' => null,
        ];
    }

    /**
     * Variant A.
     */
    public function variantA(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Variant A',
            'variant_code' => 'a',
        ]);
    }

    /**
     * Variant B.
     */
    public function variantB(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Variant B',
            'variant_code' => 'b',
        ]);
    }

    /**
     * Active variant.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive variant.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Winner variant.
     */
    public function winner(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_winner' => true,
            'traffic_percentage' => 100,
            'declared_winner_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Control variant (no changes from original).
     */
    public function control(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Control',
            'variant_code' => 'control',
            'content' => null, // Uses original page content
            'styles' => null,
        ]);
    }

    /**
     * With specific traffic percentage.
     */
    public function withTraffic(int $percentage): static
    {
        return $this->state(fn (array $attributes) => [
            'traffic_percentage' => $percentage,
        ]);
    }
}
