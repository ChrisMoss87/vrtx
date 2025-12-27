<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Call\Entities\CallProvider;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Call\Entities\CallProvider>
 */
class CallProviderFactory extends Factory
{
    protected $model = CallProvider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Main Phone Line', 'Sales Line', 'Support Line']),
            'provider' => $this->faker->randomElement(['twilio', 'vonage', 'aircall']),
            'account_sid' => 'AC' . $this->faker->regexify('[a-f0-9]{32}'),
            'auth_token' => encrypt($this->faker->regexify('[a-f0-9]{32}')),
            'phone_number' => '+1' . $this->faker->numerify('##########'),
            'is_active' => true,
            'settings' => [
                'webhook_url' => 'https://example.com/webhooks/calls',
                'record_calls' => true,
                'transcribe_calls' => false,
            ],
        ];
    }

    /**
     * Twilio provider.
     */
    public function twilio(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'twilio',
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

    /**
     * With transcription enabled.
     */
    public function withTranscription(): static
    {
        return $this->state(function (array $attributes) {
            $settings = $attributes['settings'] ?? [];
            $settings['transcribe_calls'] = true;

            return ['settings' => $settings];
        });
    }
}
