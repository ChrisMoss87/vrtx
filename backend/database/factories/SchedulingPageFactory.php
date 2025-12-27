<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Scheduling\Entities\SchedulingPage;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Scheduling\Entities\SchedulingPage>
 */
class SchedulingPageFactory extends Factory
{
    protected $model = SchedulingPage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name() . "'s Calendar";

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'timezone' => 'America/New_York',
            'branding' => [
                'primary_color' => '#3B82F6',
                'logo_url' => null,
            ],
        ];
    }

    /**
     * Active page.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive page.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * With custom branding.
     */
    public function withBranding(): static
    {
        return $this->state(fn (array $attributes) => [
            'branding' => [
                'primary_color' => $this->faker->hexColor(),
                'logo_url' => 'https://example.com/logo.png',
                'header_text' => 'Schedule a meeting with us',
            ],
        ]);
    }
}
