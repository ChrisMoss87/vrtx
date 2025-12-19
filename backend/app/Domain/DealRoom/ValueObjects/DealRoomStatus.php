<?php

declare(strict_types=1);

namespace App\Domain\DealRoom\ValueObjects;

enum DealRoomStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case CLOSED_WON = 'closed_won';
    case CLOSED_LOST = 'closed_lost';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::CLOSED_WON => 'Closed Won',
            self::CLOSED_LOST => 'Closed Lost',
            self::ARCHIVED => 'Archived',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::DRAFT, self::ACTIVE]);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::CLOSED_WON, self::CLOSED_LOST, self::ARCHIVED]);
    }
}
