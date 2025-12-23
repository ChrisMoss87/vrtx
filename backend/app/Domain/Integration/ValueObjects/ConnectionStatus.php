<?php

declare(strict_types=1);

namespace App\Domain\Integration\ValueObjects;

enum ConnectionStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ERROR = 'error';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Connected',
            self::INACTIVE => 'Disconnected',
            self::ERROR => 'Error',
            self::EXPIRED => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::INACTIVE => 'gray',
            self::ERROR => 'red',
            self::EXPIRED => 'yellow',
        };
    }

    public function isConnected(): bool
    {
        return $this === self::ACTIVE;
    }

    public function requiresReconnection(): bool
    {
        return $this === self::ERROR || $this === self::EXPIRED;
    }
}
