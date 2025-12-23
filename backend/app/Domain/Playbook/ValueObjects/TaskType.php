<?php

declare(strict_types=1);

namespace App\Domain\Playbook\ValueObjects;

enum TaskType: string
{
    case EMAIL = 'email';
    case CALL = 'call';
    case TODO = 'todo';
    case MEETING = 'meeting';
    case FOLLOW_UP = 'follow_up';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::CALL => 'Call',
            self::TODO => 'To-Do',
            self::MEETING => 'Meeting',
            self::FOLLOW_UP => 'Follow Up',
            self::CUSTOM => 'Custom',
        };
    }
}
