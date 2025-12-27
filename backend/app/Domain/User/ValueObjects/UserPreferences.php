<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

use JsonSerializable;

final readonly class UserPreferences implements JsonSerializable
{
    public function __construct(
        private array $preferences = [],
    ) {}

    public static function fromArray(array $preferences): self
    {
        return new self($preferences);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->preferences[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->preferences);
    }

    public function with(string $key, mixed $value): self
    {
        $preferences = $this->preferences;
        $preferences[$key] = $value;

        return new self($preferences);
    }

    public function without(string $key): self
    {
        $preferences = $this->preferences;
        unset($preferences[$key]);

        return new self($preferences);
    }

    public function merge(array $preferences): self
    {
        return new self(array_merge($this->preferences, $preferences));
    }

    public function toArray(): array
    {
        return $this->preferences;
    }

    public function jsonSerialize(): array
    {
        return $this->preferences;
    }

    public function isEmpty(): bool
    {
        return empty($this->preferences);
    }
}
