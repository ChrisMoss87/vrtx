<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\ValueObjects;

/**
 * AdjustmentType enum.
 *
 * Defines types of forecast adjustments that can be made.
 */
enum AdjustmentType: string
{
    case CATEGORY_CHANGE = 'category_change';
    case AMOUNT_OVERRIDE = 'amount_override';
    case CLOSE_DATE_CHANGE = 'close_date_change';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CATEGORY_CHANGE => 'Forecast Category Changed',
            self::AMOUNT_OVERRIDE => 'Amount Override',
            self::CLOSE_DATE_CHANGE => 'Close Date Changed',
        };
    }

    /**
     * Get description.
     */
    public function description(): string
    {
        return match ($this) {
            self::CATEGORY_CHANGE => 'Forecast category was manually changed',
            self::AMOUNT_OVERRIDE => 'Deal amount was manually overridden for forecast',
            self::CLOSE_DATE_CHANGE => 'Expected close date was changed',
        };
    }

    /**
     * Get icon for display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::CATEGORY_CHANGE => 'tag',
            self::AMOUNT_OVERRIDE => 'currency-dollar',
            self::CLOSE_DATE_CHANGE => 'calendar',
        };
    }

    /**
     * Get all adjustment types as array.
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
                'icon' => $case->icon(),
            ];
        }
        return $result;
    }
}
