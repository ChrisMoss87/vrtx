<?php

declare(strict_types=1);

namespace App\Domain\Campaign\ValueObjects;

enum CampaignType: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }
}
