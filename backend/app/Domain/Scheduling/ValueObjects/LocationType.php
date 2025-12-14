<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\ValueObjects;

/**
 * Enum representing meeting location types.
 */
enum LocationType: string
{
    case ZOOM = 'zoom';
    case GOOGLE_MEET = 'google_meet';
    case PHONE = 'phone';
    case IN_PERSON = 'in_person';
    case CUSTOM = 'custom';

    /**
     * Get human-readable label for this location type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ZOOM => 'Zoom',
            self::GOOGLE_MEET => 'Google Meet',
            self::PHONE => 'Phone Call',
            self::IN_PERSON => 'In Person',
            self::CUSTOM => 'Custom',
        };
    }

    /**
     * Check if this location type requires a link or details.
     */
    public function requiresDetails(): bool
    {
        return in_array($this, [self::IN_PERSON, self::CUSTOM]);
    }

    /**
     * Check if this location type is virtual.
     */
    public function isVirtual(): bool
    {
        return in_array($this, [self::ZOOM, self::GOOGLE_MEET, self::PHONE]);
    }

    /**
     * Get default location text for this type.
     */
    public function defaultLocationText(): string
    {
        return match ($this) {
            self::ZOOM => 'Zoom meeting (link will be sent via email)',
            self::GOOGLE_MEET => 'Google Meet (link will be sent via email)',
            self::PHONE => 'Phone call',
            self::IN_PERSON => 'In person',
            self::CUSTOM => '',
        };
    }
}
