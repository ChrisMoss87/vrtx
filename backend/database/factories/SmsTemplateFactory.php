<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Sms\Entities\SmsTemplate;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Sms\Entities\SmsTemplate>
 */
class SmsTemplateFactory extends Factory
{
    protected $model = SmsTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = $this->faker->randomElement([
            'Hi {{name}}, thanks for your interest! Reply YES to learn more.',
            'Reminder: Your appointment with {{company}} is tomorrow at {{time}}.',
            'Hi {{name}}, your order #{{order_number}} has shipped!',
            'Don\'t miss out! Our sale ends tonight. Use code SAVE20.',
            'Hi {{name}}, we\'ve received your request and will respond shortly.',
        ]);

        return [
            'name' => $this->faker->randomElement([
                'Welcome Message',
                'Appointment Reminder',
                'Order Notification',
                'Promotional Message',
                'Follow-up Template',
            ]),
            'content' => $content,
            'category' => $this->faker->randomElement(['transactional', 'promotional', 'reminder', 'notification']),
            'is_active' => true,
            'merge_fields' => SmsTemplate::extractMergeFields($content),
            'character_count' => strlen($content),
            'segment_count' => SmsTemplate::calculateSegments($content),
            'created_by' => User::factory(),
            'usage_count' => $this->faker->numberBetween(0, 500),
            'last_used_at' => $this->faker->optional(0.7)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Transactional template.
     */
    public function transactional(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'transactional',
            'name' => 'Order Update',
            'content' => 'Hi {{name}}, your order #{{order_number}} status: {{status}}',
        ]);
    }

    /**
     * Promotional template.
     */
    public function promotional(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'promotional',
            'name' => 'Promotional Offer',
            'content' => 'Special offer for you {{name}}! Get 20% off. Use code: SAVE20. Reply STOP to opt out.',
        ]);
    }

    /**
     * Reminder template.
     */
    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'reminder',
            'name' => 'Appointment Reminder',
            'content' => 'Reminder: You have an appointment tomorrow at {{time}}. Reply C to confirm.',
        ]);
    }

    /**
     * Active template.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Inactive template.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Popular template.
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(1000, 5000),
            'last_used_at' => now(),
        ]);
    }
}
