<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IncomingWebhook;
use App\Models\IncomingWebhookLog;
use App\Models\ModuleRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomingWebhookLog>
 */
class IncomingWebhookLogFactory extends Factory
{
    protected $model = IncomingWebhookLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incoming_webhook_id' => IncomingWebhook::factory(),
            'payload' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'phone' => fake()->phoneNumber(),
            ],
            'status' => IncomingWebhookLog::STATUS_SUCCESS,
            'record_id' => null,
            'error_message' => null,
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }

    /**
     * Mark as successful with created record.
     */
    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncomingWebhookLog::STATUS_SUCCESS,
            'record_id' => fake()->randomNumber(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark as failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncomingWebhookLog::STATUS_FAILED,
            'record_id' => null,
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Mark as invalid.
     */
    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncomingWebhookLog::STATUS_INVALID,
            'record_id' => null,
            'error_message' => 'Invalid payload: missing required fields',
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
     * Attach to specific incoming webhook.
     */
    public function forIncomingWebhook(IncomingWebhook $webhook): static
    {
        return $this->state(fn (array $attributes) => [
            'incoming_webhook_id' => $webhook->id,
        ]);
    }

    /**
     * Set created record.
     */
    public function forRecord(ModuleRecord $record): static
    {
        return $this->state(fn (array $attributes) => [
            'record_id' => $record->id,
            'status' => IncomingWebhookLog::STATUS_SUCCESS,
        ]);
    }
}
