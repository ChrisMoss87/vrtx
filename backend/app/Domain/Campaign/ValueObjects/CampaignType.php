<?php

declare(strict_types=1);

namespace App\Domain\Campaign\ValueObjects;

/**
 * Value Object representing the type of a marketing campaign.
 */
enum CampaignType: string
{
    case Email = 'email';
    case Drip = 'drip';
    case Event = 'event';
    case ProductLaunch = 'product_launch';
    case Newsletter = 'newsletter';
    case ReEngagement = 're_engagement';

    /**
     * Get the display label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email Campaign',
            self::Drip => 'Drip Sequence',
            self::Event => 'Event Promotion',
            self::ProductLaunch => 'Product Launch',
            self::Newsletter => 'Newsletter',
            self::ReEngagement => 'Re-engagement',
        };
    }

    /**
     * Get the icon name for this type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Email => 'mail',
            self::Drip => 'droplet',
            self::Event => 'calendar-event',
            self::ProductLaunch => 'rocket',
            self::Newsletter => 'newspaper',
            self::ReEngagement => 'refresh-cw',
        };
    }

    /**
     * Get the color for this type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Email => 'blue',
            self::Drip => 'cyan',
            self::Event => 'purple',
            self::ProductLaunch => 'orange',
            self::Newsletter => 'green',
            self::ReEngagement => 'yellow',
        };
    }

    /**
     * Check if this type supports automation.
     */
    public function supportsAutomation(): bool
    {
        return match ($this) {
            self::Drip, self::ReEngagement => true,
            default => false,
        };
    }

    /**
     * Check if this type is a one-time send.
     */
    public function isOneTimeSend(): bool
    {
        return match ($this) {
            self::Email, self::Event, self::ProductLaunch, self::Newsletter => true,
            default => false,
        };
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
