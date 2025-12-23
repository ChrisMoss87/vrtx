<?php

declare(strict_types=1);

namespace App\Domain\Inbox\ValueObjects;

/**
 * Value Object representing the priority of an inbox conversation.
 */
enum ConversationPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * Get the display label for this priority.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    /**
     * Get the color for this priority.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'blue',
            self::High => 'orange',
            self::Urgent => 'red',
        };
    }

    /**
     * Get the numeric weight for sorting.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Normal => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }

    /**
     * Check if this is a high-priority conversation.
     */
    public function isHighPriority(): bool
    {
        return match ($this) {
            self::High, self::Urgent => true,
            default => false,
        };
    }

    /**
     * Get all priorities as an associative array.
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
