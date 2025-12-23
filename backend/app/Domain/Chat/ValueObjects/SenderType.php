<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

enum SenderType: string
{
    case VISITOR = 'visitor';
    case AGENT = 'agent';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::VISITOR => 'Visitor',
            self::AGENT => 'Agent',
            self::SYSTEM => 'System',
        };
    }

    public function isVisitor(): bool
    {
        return $this === self::VISITOR;
    }

    public function isAgent(): bool
    {
        return $this === self::AGENT;
    }

    public function isSystem(): bool
    {
        return $this === self::SYSTEM;
    }
}
