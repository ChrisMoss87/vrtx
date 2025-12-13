<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $events = ['record.created', 'record.updated', 'record.deleted', 'stage.changed'];

        return [
            'webhook_id' => Webhook::factory(),
            'event' => fake()->randomElement($events),
            'payload' => [
                'event' => fake()->randomElement($events),
                'record_id' => fake()->randomNumber(),
                'timestamp' => now()->toISOString(),
            ],
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
            'response_code' => null,
            'response_body' => null,
            'error_message' => null,
            'response_time_ms' => null,
            'delivered_at' => null,
            'next_retry_at' => null,
        ];
    }

    /**
     * Mark as successful.
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookDelivery::STATUS_SUCCESS,
            'attempts' => 1,
            'response_code' => 200,
            'response_body' => '{"success":true}',
            'response_time_ms' => fake()->numberBetween(50, 500),
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark as failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookDelivery::STATUS_FAILED,
            'attempts' => fake()->numberBetween(1, 3),
            'response_code' => fake()->randomElement([500, 502, 503, 504, null]),
            'error_message' => fake()->sentence(),
            'response_time_ms' => fake()->optional()->numberBetween(1000, 30000),
        ]);
    }

    /**
     * Mark as pending retry.
     */
    public function pendingRetry(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => fake()->numberBetween(1, 2),
            'next_retry_at' => fake()->dateTimeBetween('now', '+1 hour'),
        ]);
    }

    /**
     * Set specific event.
     */
    public function forEvent(string $event): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => $event,
            'payload' => array_merge($attributes['payload'] ?? [], ['event' => $event]),
        ]);
    }

    /**
     * Set specific payload.
     */
    public function withPayload(array $payload): static
    {
        return $this->state(fn (array $attributes) => [
            'payload' => $payload,
        ]);
    }

    /**
     * Attach to specific webhook.
     */
    public function forWebhook(Webhook $webhook): static
    {
        return $this->state(fn (array $attributes) => [
            'webhook_id' => $webhook->id,
        ]);
    }
}
