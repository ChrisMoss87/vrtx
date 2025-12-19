<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\ValueObjects;

/**
 * Enum representing calendar providers.
 */
enum CalendarProvider: string
{
    case GOOGLE = 'google';
    case OUTLOOK = 'outlook';
    case APPLE = 'apple';

    /**
     * Get human-readable label for this provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::GOOGLE => 'Google Calendar',
            self::OUTLOOK => 'Microsoft Outlook',
            self::APPLE => 'Apple Calendar',
        };
    }

    /**
     * Get the OAuth provider name.
     */
    public function oauthProvider(): string
    {
        return match ($this) {
            self::GOOGLE => 'google',
            self::OUTLOOK => 'microsoft',
            self::APPLE => 'apple',
        };
    }

    /**
     * Check if this provider supports OAuth refresh tokens.
     */
    public function supportsRefreshToken(): bool
    {
        return match ($this) {
            self::GOOGLE, self::OUTLOOK => true,
            self::APPLE => false,
        };
    }
}
