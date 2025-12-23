<?php

declare(strict_types=1);

namespace App\Domain\Inbox\ValueObjects;

/**
 * Value Object representing the type of an inbox message.
 */
enum MessageType: string
{
    case Original = 'original';
    case Reply = 'reply';
    case Forward = 'forward';
    case Note = 'note';
    case AutoReply = 'auto_reply';

    /**
     * Get the display label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Original => 'Original',
            self::Reply => 'Reply',
            self::Forward => 'Forward',
            self::Note => 'Internal Note',
            self::AutoReply => 'Auto Reply',
        };
    }

    /**
     * Get the icon for this type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Original => 'mail',
            self::Reply => 'reply',
            self::Forward => 'forward',
            self::Note => 'sticky-note',
            self::AutoReply => 'bot',
        };
    }

    /**
     * Check if message is visible to customer.
     */
    public function isVisibleToCustomer(): bool
    {
        return $this !== self::Note;
    }

    /**
     * Check if this is an internal message.
     */
    public function isInternal(): bool
    {
        return $this === self::Note;
    }

    /**
     * Check if this is an automated message.
     */
    public function isAutomated(): bool
    {
        return $this === self::AutoReply;
    }

    /**
     * Get all types as an associative array.
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
