<?php

declare(strict_types=1);

namespace App\Domain\Cadence\ValueObjects;

enum CadenceStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::ARCHIVED => 'Archived',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canEnroll(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canActivate(): bool
    {
        return $this === self::DRAFT || $this === self::PAUSED;
    }

    public function canPause(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canArchive(): bool
    {
        return $this !== self::ARCHIVED;
    }
}
