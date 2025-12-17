<?php

declare(strict_types=1);

namespace App\Domain\CMS\ValueObjects;

enum CommentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case SPAM = 'spam';
    case TRASH = 'trash';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::SPAM => 'Spam',
            self::TRASH => 'Trash',
        };
    }

    public function isVisible(): bool
    {
        return $this === self::APPROVED;
    }
}
