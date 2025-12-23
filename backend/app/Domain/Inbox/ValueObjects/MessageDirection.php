<?php

declare(strict_types=1);

namespace App\Domain\Inbox\ValueObjects;

/**
 * Value Object representing the direction of an inbox message.
 */
enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    /**
     * Get the display label for this direction.
     */
    public function label(): string
    {
        return match ($this) {
            self::Inbound => 'Inbound',
            self::Outbound => 'Outbound',
        };
    }

    /**
     * Get the icon for this direction.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Inbound => 'arrow-down-left',
            self::Outbound => 'arrow-up-right',
        };
    }

    /**
     * Check if message is from customer.
     */
    public function isFromCustomer(): bool
    {
        return $this === self::Inbound;
    }

    /**
     * Check if message is to customer.
     */
    public function isToCustomer(): bool
    {
        return $this === self::Outbound;
    }

    /**
     * Get all directions as an associative array.
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
