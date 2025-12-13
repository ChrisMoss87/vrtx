<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

/**
 * Represents the type of a blueprint state.
 */
enum StateType: string
{
    case INITIAL = 'initial';
    case INTERMEDIATE = 'intermediate';
    case TERMINAL = 'terminal';

    public function isInitial(): bool
    {
        return $this === self::INITIAL;
    }

    public function isTerminal(): bool
    {
        return $this === self::TERMINAL;
    }

    public function label(): string
    {
        return match ($this) {
            self::INITIAL => 'Initial',
            self::INTERMEDIATE => 'Intermediate',
            self::TERMINAL => 'Terminal',
        };
    }
}
