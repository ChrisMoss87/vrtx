<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Email\Entities\EmailMessage;

use App\Domain\User\Entities\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Email\Entities\EmailMessage>
 */
class EmailMessageFactory extends Factory
{
    protected $model = EmailMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_account_id' => EmailAccount::factory(),
            'user_id' => User::factory(),
            'message_id' => '<' . $this->faker->uuid() . '@example.com>',
            'thread_id' => null,
            'parent_id' => null,
            'subject' => $this->faker->sentence(),
            'from_address' => $this->faker->email(),
            'from_name' => $this->faker->name(),
            'to_addresses' => [$this->faker->email()],
            'cc_addresses' => [],
            'bcc_addresses' => [],
            'reply_to' => null,
            'body_html' => '<p>' . $this->faker->paragraphs(3, true) . '</p>',
            'body_text' => $this->faker->paragraphs(3, true),
            'status' => 'received',
            'direction' => 'inbound',
            'is_read' => false,
            'is_starred' => false,
            'folder' => 'inbox',
            'attachments' => [],
            'headers' => [],
            'sent_at' => now(),
            'received_at' => now(),
        ];
    }

    /**
     * Create an inbound email.
     */
    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'inbound',
            'status' => 'received',
            'folder' => 'inbox',
        ]);
    }

    /**
     * Create an outbound email.
     */
    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'outbound',
            'status' => 'sent',
            'folder' => 'sent',
        ]);
    }

    /**
     * Create a draft email.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'outbound',
            'status' => 'draft',
            'folder' => 'drafts',
            'sent_at' => null,
        ]);
    }

    /**
     * Mark email as read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    /**
     * Mark email as unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
        ]);
    }

    /**
     * Mark email as starred.
     */
    public function starred(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_starred' => true,
        ]);
    }

    /**
     * Set in a specific folder.
     */
    public function inFolder(string $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'folder' => $folder,
        ]);
    }

    /**
     * Add attachments.
     */
    public function withAttachments(array $attachments = []): static
    {
        return $this->state(fn (array $attributes) => [
            'attachments' => $attachments ?: [
                ['name' => 'document.pdf', 'size' => 1024, 'mime' => 'application/pdf'],
            ],
        ]);
    }

    /**
     * Create a reply to another message.
     */
    public function replyTo(EmailMessage $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'thread_id' => $parent->thread_id ?? $parent->id,
            'subject' => 'Re: ' . $parent->subject,
        ]);
    }

    /**
     * Create a scheduled email.
     */
    public function scheduled(\DateTime $sendAt = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'direction' => 'outbound',
            'folder' => 'drafts',
            'sent_at' => null,
            'scheduled_at' => $sendAt ?? now()->addHours(2),
        ]);
    }
}
