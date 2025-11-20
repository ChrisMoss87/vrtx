<?php

declare(strict_types=1);

namespace Domain\TenantManagement\ValueObjects;

use Domain\TenantManagement\Exceptions\InvalidDomainNameException;

final readonly class DomainName
{
    private function __construct(
        private string $value
    ) {
    }

    public static function from(string $domain): self
    {
        $domain = trim(strtolower($domain));

        // Basic domain validation
        if (!preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/', $domain)) {
            throw new InvalidDomainNameException("Invalid domain name: {$domain}");
        }

        return new self($domain);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isSubdomain(DomainName $parent): bool
    {
        return str_ends_with($this->value, '.' . $parent->value);
    }

    public function equals(DomainName $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
