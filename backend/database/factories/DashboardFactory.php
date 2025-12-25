<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dashboard>
 */
class DashboardFactory extends Factory
{
    protected $model = Dashboard::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true) . ' Dashboard',
            'description' => $this->faker->sentence(),
            'user_id' => User::factory(),
            'is_default' => false,
            'is_public' => false,
            'layout' => [],
            'settings' => [],
            'filters' => [],
            'refresh_interval' => 0,
        ];
    }

    /**
     * Mark dashboard as default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Mark dashboard as public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Set auto-refresh interval.
     */
    public function withRefresh(int $seconds = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'refresh_interval' => $seconds,
        ]);
    }

    /**
     * Set a layout configuration.
     */
    public function withLayout(array $layout): static
    {
        return $this->state(fn (array $attributes) => [
            'layout' => $layout,
        ]);
    }

    /**
     * Set settings.
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => $settings,
        ]);
    }
}
