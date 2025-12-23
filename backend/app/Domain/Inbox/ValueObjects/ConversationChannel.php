<?php

declare(strict_types=1);

namespace App\Domain\Inbox\ValueObjects;

/**
 * Value Object representing the channel source of an inbox conversation.
 */
enum ConversationChannel: string
{
    case Email = 'email';
    case WebForm = 'web_form';
    case Chat = 'chat';
    case Api = 'api';
    case Manual = 'manual';

    /**
     * Get the display label for this channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::WebForm => 'Web Form',
            self::Chat => 'Live Chat',
            self::Api => 'API',
            self::Manual => 'Manual',
        };
    }

    /**
     * Get the icon for this channel.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Email => 'mail',
            self::WebForm => 'globe',
            self::Chat => 'message-circle',
            self::Api => 'code',
            self::Manual => 'plus-circle',
        };
    }

    /**
     * Check if channel supports real-time responses.
     */
    public function isRealTime(): bool
    {
        return $this === self::Chat;
    }

    /**
     * Get all channels as an associative array.
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }
}
