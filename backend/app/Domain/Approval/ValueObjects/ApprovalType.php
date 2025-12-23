<?php

declare(strict_types=1);

namespace App\Domain\Approval\ValueObjects;

/**
 * Value Object representing the type of approval flow.
 */
enum ApprovalType: string
{
    case Sequential = 'sequential';
    case Parallel = 'parallel';
    case Any = 'any';

    /**
     * Get the display label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sequential => 'Sequential (one after another)',
            self::Parallel => 'Parallel (all at once, all must approve)',
            self::Any => 'Any (first approval wins)',
        };
    }

    /**
     * Check if all approvers are required.
     */
    public function requiresAll(): bool
    {
        return $this !== self::Any;
    }

    /**
     * Check if this is a sequential approval flow.
     */
    public function isSequential(): bool
    {
        return $this === self::Sequential;
    }

    /**
     * Check if this is a parallel approval flow.
     */
    public function isParallel(): bool
    {
        return $this === self::Parallel;
    }

    /**
     * Get all types as an associative array.
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
