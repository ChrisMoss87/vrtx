<?php

declare(strict_types=1);

namespace App\Domain\Approval\ValueObjects;

enum ApprovalType: string
{
    case SEQUENTIAL = 'sequential';
    case PARALLEL = 'parallel';
    case ANY = 'any';

    public function label(): string
    {
        return match ($this) {
            self::SEQUENTIAL => 'Sequential (one after another)',
            self::PARALLEL => 'Parallel (all at once, all must approve)',
            self::ANY => 'Any (first approval wins)',
        };
    }

    public function requiresAllApprovers(): bool
    {
        return $this !== self::ANY;
    }
}
