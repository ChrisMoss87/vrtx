<?php

declare(strict_types=1);

namespace App\Domain\Playbook\ValueObjects;

enum AssigneeType: string
{
    case USER = 'user';
    case ROLE = 'role';
    case OWNER = 'owner';
    case CREATOR = 'creator';

    public function label(): string
    {
        return match ($this) {
            self::USER => 'Specific User',
            self::ROLE => 'Role',
            self::OWNER => 'Record Owner',
            self::CREATOR => 'Record Creator',
        };
    }
}
