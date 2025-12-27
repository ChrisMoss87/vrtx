<?php

declare(strict_types=1);

namespace App\Domain\Plugin\ValueObjects;

use InvalidArgumentException;

final readonly class LicenseStatus
{
    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';
    public const SUSPENDED = 'suspended';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::EXPIRED,
        self::CANCELLED,
        self::SUSPENDED,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid license status "%s". Valid statuses: %s',
                    $value,
                    implode(', ', self::VALID_STATUSES)
                )
            );
        }
    }

    public static function fromString(string $status): self
    {
        return new self($status);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function suspended(): self
    {
        return new self(self::SUSPENDED);
    }

    public static function validStatuses(): array
    {
        return self::VALID_STATUSES;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->value === self::EXPIRED;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function isSuspended(): bool
    {
        return $this->value === self::SUSPENDED;
    }

    public function isValid(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function canBeReactivated(): bool
    {
        return in_array($this->value, [self::EXPIRED, self::CANCELLED, self::SUSPENDED], true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
