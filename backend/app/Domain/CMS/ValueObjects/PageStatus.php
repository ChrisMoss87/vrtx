<?php

declare(strict_types=1);

namespace App\Domain\CMS\ValueObjects;

enum PageStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case SCHEDULED = 'scheduled';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_REVIEW => 'Pending Review',
            self::SCHEDULED => 'Scheduled',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_REVIEW => 'yellow',
            self::SCHEDULED => 'blue',
            self::PUBLISHED => 'green',
            self::ARCHIVED => 'red',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function canEdit(): bool
    {
        return $this !== self::ARCHIVED;
    }

    public function canPublish(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING_REVIEW, self::SCHEDULED]);
    }
}
