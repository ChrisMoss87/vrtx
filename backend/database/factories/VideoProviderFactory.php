<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Video\Entities\VideoProvider;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Video\Entities\VideoProvider>
 */
class VideoProviderFactory extends Factory
{
    protected $model = VideoProvider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Zoom', 'Google Meet', 'Microsoft Teams']),
            'provider' => $this->faker->randomElement(['zoom', 'google_meet', 'ms_teams']),
            'is_active' => true,
            'client_id' => $this->faker->regexify('[A-Za-z0-9]{22}'),
            'api_secret' => $this->faker->regexify('[A-Za-z0-9]{32}'),
            'settings' => [
                'default_waiting_room' => true,
                'default_recording' => false,
                'auto_transcription' => false,
            ],
        ];
    }

    /**
     * Zoom provider.
     */
    public function zoom(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Zoom',
            'provider' => 'zoom',
        ]);
    }

    /**
     * Google Meet provider.
     */
    public function googleMeet(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Google Meet',
            'provider' => 'google_meet',
        ]);
    }

    /**
     * Microsoft Teams provider.
     */
    public function teams(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Microsoft Teams',
            'provider' => 'ms_teams',
        ]);
    }

    /**
     * Active provider.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive provider.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
