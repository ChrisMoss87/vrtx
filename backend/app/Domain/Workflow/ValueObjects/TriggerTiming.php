<?php

declare(strict_types=1);

namespace App\Domain\Workflow\ValueObjects;

/**
 * Enum representing when a workflow should trigger relative to create/update.
 */
enum TriggerTiming: string
{
    case ALL = 'all';
    case CREATE_ONLY = 'create_only';
    case UPDATE_ONLY = 'update_only';

    /**
     * Get human-readable label for this timing.
     */
    public function label(): string
    {
        return match ($this) {
            self::ALL => 'On create and update',
            self::CREATE_ONLY => 'Only on create',
            self::UPDATE_ONLY => 'Only on update',
        };
    }

    /**
     * Check if this timing matches the given operation.
     */
    public function matches(bool $isCreate): bool
    {
        return match ($this) {
            self::ALL => true,
            self::CREATE_ONLY => $isCreate,
            self::UPDATE_ONLY => !$isCreate,
        };
    }

    /**
     * Get all timings as an array.
     *
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }
}
