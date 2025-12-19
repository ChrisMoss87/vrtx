<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsAlertSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnalyticsAlertSubscription>
 */
class AnalyticsAlertSubscriptionFactory extends Factory
{
    protected $model = AnalyticsAlertSubscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_id' => AnalyticsAlert::factory(),
            'user_id' => User::factory(),
            'channels' => $this->faker->randomElements(
                ['email', 'in_app', 'slack'],
                $this->faker->numberBetween(1, 3)
            ),
            'is_muted' => false,
            'muted_until' => null,
        ];
    }

    /**
     * Email channel only.
     */
    public function emailOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'channels' => ['email'],
        ]);
    }

    /**
     * In-app notification only.
     */
    public function inAppOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'channels' => ['in_app'],
        ]);
    }

    /**
     * All channels.
     */
    public function allChannels(): static
    {
        return $this->state(fn (array $attributes) => [
            'channels' => ['email', 'in_app', 'slack'],
        ]);
    }

    /**
     * Muted subscription.
     */
    public function muted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_muted' => true,
            'muted_until' => null,
        ]);
    }

    /**
     * Temporarily muted subscription.
     */
    public function mutedTemporarily(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_muted' => true,
            'muted_until' => now()->addDays($this->faker->numberBetween(1, 7)),
        ]);
    }

    /**
     * Active subscription (not muted).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_muted' => false,
            'muted_until' => null,
        ]);
    }
}
