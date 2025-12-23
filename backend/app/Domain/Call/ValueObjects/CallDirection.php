<?php

declare(strict_types=1);

namespace App\Domain\Call\ValueObjects;

/**
 * Value Object representing the direction of a call.
 */
enum CallDirection: string
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
     * Get the icon name for this direction.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Inbound => 'phone-incoming',
            self::Outbound => 'phone-outgoing',
        };
    }

    /**
     * Get the color for this direction.
     */
    public function color(): string
    {
        return match ($this) {
            self::Inbound => 'blue',
            self::Outbound => 'green',
        };
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
