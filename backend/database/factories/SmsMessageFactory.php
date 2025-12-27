<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Sms\Entities\SmsMessage;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Sms\Entities\SmsMessage>
 */
class SmsMessageFactory extends Factory
{
    protected $model = SmsMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $direction = $this->faker->randomElement(['inbound', 'outbound']);
        $status = $this->faker->randomElement(['pending', 'sent', 'delivered', 'failed']);

        return [
            'connection_id' => SmsConnection::factory(),
            'template_id' => null,
            'direction' => $direction,
            'from_number' => '+1' . $this->faker->numerify('##########'),
            'to_number' => '+1' . $this->faker->numerify('##########'),
            'content' => $this->faker->randomElement([
                'Thanks for reaching out! How can we help?',
                'Your appointment is confirmed for tomorrow at 2pm.',
                'Hi, I have a question about my account.',
                'Order shipped! Track at: example.com/track/123',
            ]),
            'status' => $status,
            'provider_message_id' => $status !== 'pending' ? 'SM' . $this->faker->regexify('[a-f0-9]{32}') : null,
            'error_code' => $status === 'failed' ? '30001' : null,
            'error_message' => $status === 'failed' ? 'Message delivery failed' : null,
            'segment_count' => $this->faker->numberBetween(1, 3),
            'cost' => $this->faker->randomFloat(4, 0.0075, 0.05),
            'module_record_id' => null,
            'module_api_name' => null,
            'campaign_id' => null,
            'sent_by' => $direction === 'outbound' ? User::factory() : null,
            'sent_at' => $direction === 'outbound' && $status !== 'pending'
                ? $this->faker->dateTimeBetween('-7 days', 'now')
                : null,
            'delivered_at' => $status === 'delivered'
                ? $this->faker->dateTimeBetween('-7 days', 'now')
                : null,
            'read_at' => null,
        ];
    }

    /**
     * Inbound message.
     */
    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'inbound',
            'status' => 'delivered',
            'sent_by' => null,
            'sent_at' => null,
        ]);
    }

    /**
     * Outbound message.
     */
    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'outbound',
            'sent_by' => User::factory(),
        ]);
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'outbound',
            'status' => 'pending',
            'provider_message_id' => null,
            'sent_at' => null,
            'delivered_at' => null,
        ]);
    }

    /**
     * Sent status.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'outbound',
            'status' => 'sent',
            'sent_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'delivered_at' => null,
        ]);
    }

    /**
     * Delivered status.
     */
    public function delivered(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-7 days', '-1 day');

        return $this->state(fn (array $attributes) => [
            'direction' => 'outbound',
            'status' => 'delivered',
            'sent_at' => $sentAt,
            'delivered_at' => $this->faker->dateTimeBetween($sentAt, 'now'),
        ]);
    }

    /**
     * Failed status.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'outbound',
            'status' => 'failed',
            'error_code' => '30001',
            'error_message' => 'Message delivery failed - invalid number',
            'delivered_at' => null,
        ]);
    }

    /**
     * Using a template.
     */
    public function fromTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'template_id' => SmsTemplate::factory(),
        ]);
    }

    /**
     * Linked to a record.
     */
    public function linkedToRecord(string $moduleApiName = 'contacts', int $recordId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'module_api_name' => $moduleApiName,
            'module_record_id' => $recordId ?? $this->faker->numberBetween(1, 100),
        ]);
    }
}
