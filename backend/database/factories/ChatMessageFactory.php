<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $senderType = $this->faker->randomElement([
            ChatMessage::SENDER_VISITOR,
            ChatMessage::SENDER_AGENT,
        ]);

        return [
            'conversation_id' => ChatConversation::factory(),
            'sender_type' => $senderType,
            'sender_id' => $senderType === ChatMessage::SENDER_AGENT ? User::factory() : null,
            'content' => $this->faker->randomElement([
                'Hi, I have a question about your pricing.',
                'Can you help me with a technical issue?',
                'I am interested in your enterprise plan.',
                'How do I integrate with my existing tools?',
                'Thanks for your help!',
                'Let me check that for you.',
                'I can help you with that!',
                'Here is the information you requested.',
            ]),
            'content_type' => ChatMessage::CONTENT_TEXT,
            'attachments' => null,
            'metadata' => null,
            'is_internal' => false,
            'read_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    /**
     * From visitor.
     */
    public function fromVisitor(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => ChatMessage::SENDER_VISITOR,
            'sender_id' => null,
            'content' => $this->faker->randomElement([
                'Hi, I have a question.',
                'Can you help me?',
                'I need some information about your product.',
                'Thanks for the quick response!',
            ]),
        ]);
    }

    /**
     * From agent.
     */
    public function fromAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => ChatMessage::SENDER_AGENT,
            'sender_id' => User::factory(),
            'content' => $this->faker->randomElement([
                'Hi! How can I help you today?',
                'Let me look into that for you.',
                'I can definitely help with that!',
                'Is there anything else I can assist with?',
            ]),
        ]);
    }

    /**
     * System message.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => ChatMessage::SENDER_SYSTEM,
            'sender_id' => null,
            'content' => $this->faker->randomElement([
                'Conversation started',
                'Agent joined the chat',
                'Conversation was transferred',
                'Chat ended',
            ]),
        ]);
    }

    /**
     * Internal note.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => ChatMessage::SENDER_AGENT,
            'sender_id' => User::factory(),
            'is_internal' => true,
            'content' => $this->faker->randomElement([
                'Customer seems frustrated, handle with care.',
                'Escalating to tier 2 support.',
                'Potential upsell opportunity here.',
            ]),
        ]);
    }

    /**
     * With attachment.
     */
    public function withAttachment(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => ChatMessage::CONTENT_FILE,
            'attachments' => [
                [
                    'name' => 'screenshot.png',
                    'url' => 'https://example.com/files/screenshot.png',
                    'size' => 125000,
                    'mime_type' => 'image/png',
                ],
            ],
        ]);
    }

    /**
     * Image message.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => ChatMessage::CONTENT_IMAGE,
            'attachments' => [
                [
                    'name' => 'image.jpg',
                    'url' => 'https://example.com/files/image.jpg',
                    'size' => 85000,
                    'mime_type' => 'image/jpeg',
                ],
            ],
        ]);
    }

    /**
     * Unread message.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }
}
