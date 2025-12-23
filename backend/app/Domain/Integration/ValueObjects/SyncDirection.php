<?php

declare(strict_types=1);

namespace App\Domain\Integration\ValueObjects;

enum SyncDirection: string
{
    case PUSH = 'push';
    case PULL = 'pull';
    case BOTH = 'both';

    public function label(): string
    {
        return match ($this) {
            self::PUSH => 'CRM → External',
            self::PULL => 'External → CRM',
            self::BOTH => 'Bi-directional',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PUSH => 'Data flows from CRM to external system only',
            self::PULL => 'Data flows from external system to CRM only',
            self::BOTH => 'Data syncs in both directions',
        };
    }
}
