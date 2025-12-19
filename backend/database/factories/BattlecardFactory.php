<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Battlecard;
use App\Models\Competitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Battlecard>
 */
class BattlecardFactory extends Factory
{
    protected $model = Battlecard::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competitor_id' => Competitor::factory(),
            'title' => $this->faker->company() . ' Competitive Battlecard',
            'sections' => [
                'overview' => $this->faker->paragraph(),
                'target_market' => 'Mid-market to Enterprise companies',
                'key_differentiators' => [
                    'Lower total cost of ownership',
                    'Faster implementation',
                    'Better customer support',
                ],
            ],
            'talking_points' => [
                'Our implementation is 50% faster',
                'We offer 24/7 support included',
                'Our mobile app has 4.8 stars vs their 3.2',
                'We integrate with 200+ apps out of the box',
            ],
            'objection_handlers' => [
                'price' => 'Compare total cost including implementation and training',
                'features' => 'Focus on the features they actually need',
                'brand' => 'Reference customers in their industry',
            ],
            'is_published' => $this->faker->boolean(80),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Published battlecard.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    /**
     * Draft battlecard.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
