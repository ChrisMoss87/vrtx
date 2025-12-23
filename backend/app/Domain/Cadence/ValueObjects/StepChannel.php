<?php

declare(strict_types=1);

namespace App\Domain\Cadence\ValueObjects;

enum StepChannel: string
{
    case EMAIL = 'email';
    case CALL = 'call';
    case SMS = 'sms';
    case LINKEDIN = 'linkedin';
    case TASK = 'task';
    case WAIT = 'wait';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::CALL => 'Call',
            self::SMS => 'SMS',
            self::LINKEDIN => 'LinkedIn',
            self::TASK => 'Task',
            self::WAIT => 'Wait',
        };
    }

    public function requiresContent(): bool
    {
        return in_array($this, [self::EMAIL, self::SMS, self::LINKEDIN], true);
    }

    public function isAutomatable(): bool
    {
        return in_array($this, [self::EMAIL, self::SMS, self::WAIT], true);
    }

    public function requiresManualAction(): bool
    {
        return in_array($this, [self::CALL, self::TASK], true);
    }
}
