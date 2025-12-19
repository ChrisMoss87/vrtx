<?php

declare(strict_types=1);

namespace App\Domain\Email\ValueObjects;

enum EmailStatus: string
{
    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case OPENED = 'opened';
    case CLICKED = 'clicked';
    case BOUNCED = 'bounced';
    case FAILED = 'failed';
    case SPAM = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::QUEUED => 'Queued',
            self::SENDING => 'Sending',
            self::SENT => 'Sent',
            self::DELIVERED => 'Delivered',
            self::OPENED => 'Opened',
            self::CLICKED => 'Clicked',
            self::BOUNCED => 'Bounced',
            self::FAILED => 'Failed',
            self::SPAM => 'Marked as Spam',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::QUEUED, self::SENDING => 'blue',
            self::SENT, self::DELIVERED => 'green',
            self::OPENED, self::CLICKED => 'emerald',
            self::BOUNCED, self::FAILED, self::SPAM => 'red',
        };
    }

    public function isDelivered(): bool
    {
        return in_array($this, [self::DELIVERED, self::OPENED, self::CLICKED]);
    }

    public function isFailed(): bool
    {
        return in_array($this, [self::BOUNCED, self::FAILED, self::SPAM]);
    }
}
