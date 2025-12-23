<?php

declare(strict_types=1);

namespace App\Domain\Goal\ValueObjects;

enum GoalType: string
{
    case INDIVIDUAL = 'individual';
    case TEAM = 'team';
    case COMPANY = 'company';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Individual',
            self::TEAM => 'Team',
            self::COMPANY => 'Company',
        };
    }

    public function isIndividual(): bool
    {
        return $this === self::INDIVIDUAL;
    }

    public function isTeam(): bool
    {
        return $this === self::TEAM;
    }

    public function isCompany(): bool
    {
        return $this === self::COMPANY;
    }
}
