<?php

declare(strict_types=1);

namespace App\Domain\Cadence\ValueObjects;

enum LinkedInAction: string
{
    case CONNECTION_REQUEST = 'connection_request';
    case MESSAGE = 'message';
    case VIEW_PROFILE = 'view_profile';
    case ENGAGE = 'engage';

    public function label(): string
    {
        return match ($this) {
            self::CONNECTION_REQUEST => 'Connection Request',
            self::MESSAGE => 'Message',
            self::VIEW_PROFILE => 'View Profile',
            self::ENGAGE => 'Engage',
        };
    }

    public function requiresMessage(): bool
    {
        return in_array($this, [self::CONNECTION_REQUEST, self::MESSAGE], true);
    }
}
