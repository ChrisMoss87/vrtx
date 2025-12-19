<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Pipeline;
use App\Models\RottingAlertSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RottingAlertSetting>
 */
class RottingAlertSettingFactory extends Factory
{
    protected $model = RottingAlertSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pipeline_id' => null,
            'email_digest_enabled' => true,
            'digest_frequency' => RottingAlertSetting::FREQUENCY_DAILY,
            'in_app_notifications' => true,
            'exclude_weekends' => false,
        ];
    }

    /**
     * Set as global setting (no specific pipeline).
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'pipeline_id' => null,
        ]);
    }

    /**
     * Set for a specific pipeline.
     */
    public function forPipeline(Pipeline $pipeline): static
    {
        return $this->state(fn (array $attributes) => [
            'pipeline_id' => $pipeline->id,
        ]);
    }

    /**
     * Set for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Disable email digest.
     */
    public function noEmailDigest(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_digest_enabled' => false,
        ]);
    }

    /**
     * Set weekly digest frequency.
     */
    public function weeklyDigest(): static
    {
        return $this->state(fn (array $attributes) => [
            'digest_frequency' => RottingAlertSetting::FREQUENCY_WEEKLY,
        ]);
    }

    /**
     * Disable in-app notifications.
     */
    public function noInAppNotifications(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_app_notifications' => false,
        ]);
    }

    /**
     * Exclude weekends from calculation.
     */
    public function excludeWeekends(): static
    {
        return $this->state(fn (array $attributes) => [
            'exclude_weekends' => true,
        ]);
    }
}
