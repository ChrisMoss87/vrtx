<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SmsConnection>
 */
class SmsConnectionFactory extends Factory
{
    protected $model = SmsConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Main SMS Line',
                'Sales SMS',
                'Support SMS',
                'Marketing SMS',
            ]),
            'provider' => $this->faker->randomElement(['twilio', 'vonage', 'plivo']),
            'phone_number' => '+1' . $this->faker->numerify('##########'),
            'account_sid' => 'AC' . $this->faker->regexify('[a-f0-9]{32}'),
            'auth_token' => encrypt($this->faker->regexify('[a-f0-9]{32}')),
            'messaging_service_sid' => $this->faker->optional(0.3)->regexify('MG[a-f0-9]{32}'),
            'is_active' => true,
            'is_verified' => true,
            'capabilities' => ['sms', 'mms'],
            'settings' => [
                'webhook_url' => 'https://example.com/webhooks/sms',
                'status_callback' => true,
            ],
            'daily_limit' => 1000,
            'monthly_limit' => 25000,
            'last_used_at' => $this->faker->optional(0.7)->dateTimeBetween('-7 days', 'now'),
        ];
    }

    /**
     * Twilio provider.
     */
    public function twilio(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'twilio',
            'account_sid' => 'AC' . $this->faker->regexify('[a-f0-9]{32}'),
        ]);
    }

    /**
     * Vonage provider.
     */
    public function vonage(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'vonage',
            'account_sid' => $this->faker->regexify('[a-f0-9]{8}'),
        ]);
    }

    /**
     * Active connection.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'is_verified' => true,
        ]);
    }

    /**
     * Inactive connection.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Unverified connection.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
        ]);
    }

    /**
     * SMS only capability.
     */
    public function smsOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'capabilities' => ['sms'],
        ]);
    }

    /**
     * High volume limits.
     */
    public function highVolume(): static
    {
        return $this->state(fn (array $attributes) => [
            'daily_limit' => 10000,
            'monthly_limit' => 250000,
        ]);
    }
}
