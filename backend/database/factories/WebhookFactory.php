<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Module;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'module_id' => fn () => Module::where('api_name', 'deals')->first()?->id ?? Module::first()?->id,
            'name' => $this->faker->words(2, true) . ' Webhook',
            'url' => $this->faker->url(),
            'secret' => Str::random(32),
            'events' => ['record.created', 'record.updated'],
            'is_active' => true,
            'headers' => [],
            'payload_format' => 'json',
            'retry_count' => 3,
            'retry_delay' => 60,
            'last_triggered_at' => null,
            'success_count' => 0,
            'failure_count' => 0,
        ];
    }

    /**
     * Mark webhook as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Mark webhook as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set events to listen for.
     */
    public function forEvents(array $events): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => $events,
        ]);
    }

    /**
     * Listen for record created events only.
     */
    public function onRecordCreated(): static
    {
        return $this->forEvents(['record.created']);
    }

    /**
     * Listen for record updated events only.
     */
    public function onRecordUpdated(): static
    {
        return $this->forEvents(['record.updated']);
    }

    /**
     * Listen for all record events.
     */
    public function onAllRecordEvents(): static
    {
        return $this->forEvents(['record.created', 'record.updated', 'record.deleted']);
    }

    /**
     * Set custom headers.
     */
    public function withHeaders(array $headers): static
    {
        return $this->state(fn (array $attributes) => [
            'headers' => $headers,
        ]);
    }

    /**
     * Set retry configuration.
     */
    public function withRetry(int $count = 3, int $delay = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'retry_count' => $count,
            'retry_delay' => $delay,
        ]);
    }

    /**
     * Set no retries.
     */
    public function noRetry(): static
    {
        return $this->state(fn (array $attributes) => [
            'retry_count' => 0,
        ]);
    }

    /**
     * Mark as recently triggered.
     */
    public function recentlyTriggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_triggered_at' => now(),
            'success_count' => $this->faker->numberBetween(10, 100),
        ]);
    }

    /**
     * Mark as failing.
     */
    public function failing(): static
    {
        return $this->state(fn (array $attributes) => [
            'failure_count' => $this->faker->numberBetween(5, 20),
        ]);
    }
}
