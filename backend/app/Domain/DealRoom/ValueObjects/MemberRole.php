<?php

declare(strict_types=1);

namespace App\Domain\DealRoom\ValueObjects;

enum MemberRole: string
{
    case OWNER = 'owner';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';
    case GUEST = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::EDITOR => 'Editor',
            self::VIEWER => 'Viewer',
            self::GUEST => 'Guest',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::OWNER, self::EDITOR]);
    }

    public function canManageMembers(): bool
    {
        return $this === self::OWNER;
    }
}
