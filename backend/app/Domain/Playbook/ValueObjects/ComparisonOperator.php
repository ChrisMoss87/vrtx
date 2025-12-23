<?php

declare(strict_types=1);

namespace App\Domain\Playbook\ValueObjects;

enum ComparisonOperator: string
{
    case GREATER_THAN = '>';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUAL = '<=';
    case EQUAL = '=';
    case NOT_EQUAL = '!=';

    public function label(): string
    {
        return match ($this) {
            self::GREATER_THAN => 'Greater than',
            self::GREATER_THAN_OR_EQUAL => 'Greater than or equal to',
            self::LESS_THAN => 'Less than',
            self::LESS_THAN_OR_EQUAL => 'Less than or equal to',
            self::EQUAL => 'Equal to',
            self::NOT_EQUAL => 'Not equal to',
        };
    }

    public function compare(mixed $actual, mixed $target): bool
    {
        return match ($this) {
            self::GREATER_THAN => $actual > $target,
            self::GREATER_THAN_OR_EQUAL => $actual >= $target,
            self::LESS_THAN => $actual < $target,
            self::LESS_THAN_OR_EQUAL => $actual <= $target,
            self::EQUAL => $actual == $target,
            self::NOT_EQUAL => $actual != $target,
        };
    }
}
