<?php

declare(strict_types=1);

namespace App\Domain\Chat\ValueObjects;

final readonly class VisitorLocation
{
    private function __construct(
        private ?string $country,
        private ?string $city,
    ) {}

    public static function fromComponents(?string $country, ?string $city): self
    {
        return new self($country, $city);
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function toString(): ?string
    {
        $parts = array_filter([$this->city, $this->country]);
        return $parts ? implode(', ', $parts) : null;
    }

    public function equals(self $other): bool
    {
        return $this->country === $other->country
            && $this->city === $other->city;
    }

    public function hasLocation(): bool
    {
        return $this->country !== null || $this->city !== null;
    }
}
