<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

/**
 * Represents the phases of a blueprint transition.
 */
enum TransitionPhase: string
{
    case BEFORE = 'before';      // Conditions that must be met
    case DURING = 'during';      // Requirements to fulfill (forms, approvals)
    case AFTER = 'after';        // Actions to execute

    public function label(): string
    {
        return match ($this) {
            self::BEFORE => 'Before (Conditions)',
            self::DURING => 'During (Requirements)',
            self::AFTER => 'After (Actions)',
        };
    }
}
