<?php

declare(strict_types=1);

namespace Domain\TenantManagement\ValueObjects;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class TenantId
{
    private function __construct(
        private UuidInterface $value
    ) {
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4());
    }

    public static function from(string $id): self
    {
        return new self(Uuid::fromString($id));
    }

    public function value(): string
    {
        return $this->value->toString();
    }

    public function equals(TenantId $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return $this->value();
    }
}
