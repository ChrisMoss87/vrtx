<?php

declare(strict_types=1);

namespace App\Domain\Communication\ValueObjects;

enum MessageDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::INBOUND => 'Received',
            self::OUTBOUND => 'Sent',
        };
    }

    public function isInbound(): bool
    {
        return $this === self::INBOUND;
    }

    public function isOutbound(): bool
    {
        return $this === self::OUTBOUND;
    }

    public function opposite(): self
    {
        return match ($this) {
            self::INBOUND => self::OUTBOUND,
            self::OUTBOUND => self::INBOUND,
        };
    }
}
