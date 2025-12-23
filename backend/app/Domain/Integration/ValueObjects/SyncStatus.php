<?php

declare(strict_types=1);

namespace App\Domain\Integration\ValueObjects;

enum SyncStatus: string
{
    case IDLE = 'idle';
    case SYNCING = 'syncing';
    case ERROR = 'error';

    public function label(): string
    {
        return match ($this) {
            self::IDLE => 'Idle',
            self::SYNCING => 'Syncing',
            self::ERROR => 'Error',
        };
    }

    public function isBusy(): bool
    {
        return $this === self::SYNCING;
    }
}
