<?php

declare(strict_types=1);

namespace App\Domain\CollaborativeDocument\ValueObjects;

enum DocumentPermission: string
{
    case VIEW = 'view';
    case COMMENT = 'comment';
    case EDIT = 'edit';
    case OWNER = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::VIEW => 'Can view',
            self::COMMENT => 'Can comment',
            self::EDIT => 'Can edit',
            self::OWNER => 'Owner',
        };
    }

    public function canView(): bool
    {
        return true;
    }

    public function canComment(): bool
    {
        return in_array($this, [self::COMMENT, self::EDIT, self::OWNER], true);
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::EDIT, self::OWNER], true);
    }

    public function canManage(): bool
    {
        return $this === self::OWNER;
    }

    public function canDelete(): bool
    {
        return $this === self::OWNER;
    }

    public function canShare(): bool
    {
        return in_array($this, [self::EDIT, self::OWNER], true);
    }

    public function isHigherOrEqualTo(self $other): bool
    {
        $hierarchy = [
            self::VIEW->value => 1,
            self::COMMENT->value => 2,
            self::EDIT->value => 3,
            self::OWNER->value => 4,
        ];

        return $hierarchy[$this->value] >= $hierarchy[$other->value];
    }

    public static function fromString(string $value): self
    {
        return self::from($value);
    }
}
