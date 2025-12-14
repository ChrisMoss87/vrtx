<?php

declare(strict_types=1);

namespace App\Domain\Email\ValueObjects;

enum EmailType: string
{
    case MANUAL = 'manual';
    case TEMPLATE = 'template';
    case WORKFLOW = 'workflow';
    case CAMPAIGN = 'campaign';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::TEMPLATE => 'Template',
            self::WORKFLOW => 'Workflow',
            self::CAMPAIGN => 'Campaign',
            self::SYSTEM => 'System',
        };
    }
}
