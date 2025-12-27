<?php

declare(strict_types=1);

namespace App\Domain\Authorization\ValueObjects;

enum RecordAccessLevel: string
{
    case ALL = 'all';
    case TEAM = 'team';
    case OWN = 'own';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'All Records',
            self::TEAM => 'Team Records',
            self::OWN => 'Own Records Only',
            self::NONE => 'No Access',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ALL => 'Can access all records in the module',
            self::TEAM => 'Can access records owned by team members',
            self::OWN => 'Can only access records they created',
            self::NONE => 'Cannot access any records',
        };
    }

    /**
     * Check if this level grants broader access than another.
     */
    public function grantsMoreAccessThan(self $other): bool
    {
        $hierarchy = [
            self::NONE->value => 0,
            self::OWN->value => 1,
            self::TEAM->value => 2,
            self::ALL->value => 3,
        ];

        return $hierarchy[$this->value] > $hierarchy[$other->value];
    }

    /**
     * Check if this level allows access to any records.
     */
    public function allowsAccess(): bool
    {
        return $this !== self::NONE;
    }

    /**
     * Check if this level allows access to all records.
     */
    public function isUnrestricted(): bool
    {
        return $this === self::ALL;
    }

    /**
     * Get the most permissive level between two.
     */
    public function merge(self $other): self
    {
        if ($this->grantsMoreAccessThan($other)) {
            return $this;
        }

        return $other;
    }
}
