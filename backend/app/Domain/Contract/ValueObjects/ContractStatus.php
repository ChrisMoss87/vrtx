<?php

declare(strict_types=1);

namespace App\Domain\Contract\ValueObjects;

/**
 * Value Object representing the status of a contract.
 */
enum ContractStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Renewed = 'renewed';

    /**
     * Get the display label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Terminated => 'Terminated',
            self::Renewed => 'Renewed',
        };
    }

    /**
     * Get the color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'green',
            self::Expired => 'red',
            self::Terminated => 'orange',
            self::Renewed => 'blue',
        };
    }

    /**
     * Check if contract is active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if contract is editable.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if contract is terminal (no more changes possible).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Expired, self::Terminated, self::Renewed => true,
            default => false,
        };
    }

    /**
     * Check if contract can be activated.
     */
    public function canBeActivated(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if contract can be terminated.
     */
    public function canBeTerminated(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if contract can be renewed.
     */
    public function canBeRenewed(): bool
    {
        return match ($this) {
            self::Active, self::Expired => true,
            default => false,
        };
    }

    /**
     * Get all statuses as an associative array.
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
