<?php

declare(strict_types=1);

namespace App\Domain\Forecasting\ValueObjects;

/**
 * ScenarioType enum.
 *
 * Defines different types of forecast scenarios.
 */
enum ScenarioType: string
{
    case CURRENT = 'current';
    case BEST_CASE = 'best_case';
    case WORST_CASE = 'worst_case';
    case TARGET_HIT = 'target_hit';
    case CUSTOM = 'custom';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CURRENT => 'Current State',
            self::BEST_CASE => 'Best Case',
            self::WORST_CASE => 'Worst Case',
            self::TARGET_HIT => 'Target Hit',
            self::CUSTOM => 'Custom',
        };
    }

    /**
     * Get description.
     */
    public function description(): string
    {
        return match ($this) {
            self::CURRENT => 'Current state of the pipeline',
            self::BEST_CASE => 'Optimistic scenario with all potential deals',
            self::WORST_CASE => 'Conservative scenario with only high-confidence deals',
            self::TARGET_HIT => 'Scenario showing what is needed to hit target',
            self::CUSTOM => 'User-defined custom scenario',
        };
    }

    /**
     * Get icon for display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::CURRENT => 'chart-bar',
            self::BEST_CASE => 'trending-up',
            self::WORST_CASE => 'trending-down',
            self::TARGET_HIT => 'target',
            self::CUSTOM => 'adjustments',
        };
    }

    /**
     * Get color for display.
     */
    public function color(): string
    {
        return match ($this) {
            self::CURRENT => 'blue',
            self::BEST_CASE => 'green',
            self::WORST_CASE => 'orange',
            self::TARGET_HIT => 'purple',
            self::CUSTOM => 'gray',
        };
    }

    /**
     * Get all scenario types as array.
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
                'icon' => $case->icon(),
                'color' => $case->color(),
            ];
        }
        return $result;
    }
}
