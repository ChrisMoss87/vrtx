<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\ValueObjects;

/**
 * QuotaType enum.
 *
 * Defines different types of quotas that can be set.
 */
enum QuotaType: string
{
    case REVENUE = 'revenue';
    case DEALS = 'deals';
    case ACTIVITIES = 'activities';
    case CALLS = 'calls';
    case MEETINGS = 'meetings';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::REVENUE => 'Revenue Quota',
            self::DEALS => 'Deal Count Quota',
            self::ACTIVITIES => 'Activity Quota',
            self::CALLS => 'Call Quota',
            self::MEETINGS => 'Meeting Quota',
        };
    }

    /**
     * Get description.
     */
    public function description(): string
    {
        return match ($this) {
            self::REVENUE => 'Revenue target in currency amount',
            self::DEALS => 'Number of deals to close',
            self::ACTIVITIES => 'Number of activities to complete',
            self::CALLS => 'Number of calls to make',
            self::MEETINGS => 'Number of meetings to conduct',
        };
    }

    /**
     * Get the unit for this quota type.
     */
    public function unit(): string
    {
        return match ($this) {
            self::REVENUE => 'currency',
            self::DEALS => 'deals',
            self::ACTIVITIES => 'activities',
            self::CALLS => 'calls',
            self::MEETINGS => 'meetings',
        };
    }

    /**
     * Check if this quota type uses currency.
     */
    public function usesCurrency(): bool
    {
        return $this === self::REVENUE;
    }

    /**
     * Get icon name for display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::REVENUE => 'currency-dollar',
            self::DEALS => 'briefcase',
            self::ACTIVITIES => 'check-circle',
            self::CALLS => 'phone',
            self::MEETINGS => 'users',
        };
    }

    /**
     * Get all quota types as array.
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
                'unit' => $case->unit(),
                'uses_currency' => $case->usesCurrency(),
                'icon' => $case->icon(),
            ];
        }
        return $result;
    }
}
