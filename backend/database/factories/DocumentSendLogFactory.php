<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentSendLog;
use App\Models\GeneratedDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentSendLog>
 */
class DocumentSendLogFactory extends Factory
{
    protected $model = DocumentSendLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sentAt = $this->faker->dateTimeBetween('-7 days', 'now');

        return [
            'document_id' => GeneratedDocument::factory(),
            'recipient_email' => $this->faker->email(),
            'recipient_name' => $this->faker->name(),
            'subject' => $this->faker->randomElement([
                'Your document is ready for review',
                'Contract ready for signature',
                'Proposal attached for your review',
                'Quote for your consideration',
            ]),
            'message' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement([
                DocumentSendLog::STATUS_SENT,
                DocumentSendLog::STATUS_DELIVERED,
                DocumentSendLog::STATUS_OPENED,
            ]),
            'sent_at' => $sentAt,
            'delivered_at' => $this->faker->optional(0.8)->dateTimeBetween($sentAt, 'now'),
            'opened_at' => $this->faker->optional(0.5)->dateTimeBetween($sentAt, 'now'),
            'sent_by' => User::factory(),
        ];
    }

    /**
     * Sent status.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentSendLog::STATUS_SENT,
            'delivered_at' => null,
            'opened_at' => null,
        ]);
    }

    /**
     * Delivered status.
     */
    public function delivered(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-7 days', '-1 day');

        return $this->state(fn (array $attributes) => [
            'status' => DocumentSendLog::STATUS_DELIVERED,
            'sent_at' => $sentAt,
            'delivered_at' => $this->faker->dateTimeBetween($sentAt, 'now'),
            'opened_at' => null,
        ]);
    }

    /**
     * Opened status.
     */
    public function opened(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-7 days', '-2 days');
        $deliveredAt = $this->faker->dateTimeBetween($sentAt, '-1 day');

        return $this->state(fn (array $attributes) => [
            'status' => DocumentSendLog::STATUS_OPENED,
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'opened_at' => $this->faker->dateTimeBetween($deliveredAt, 'now'),
        ]);
    }

    /**
     * Bounced status.
     */
    public function bounced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentSendLog::STATUS_BOUNCED,
            'delivered_at' => null,
            'opened_at' => null,
        ]);
    }
}
