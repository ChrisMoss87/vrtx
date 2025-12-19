<?php

declare(strict_types=1);

namespace App\Domain\Workflow\ValueObjects;

use JsonSerializable;

/**
 * Value object representing workflow step action configuration.
 *
 * This is a flexible container for action-specific configuration data.
 * Each action type has its own configuration schema.
 */
final readonly class ActionConfig implements JsonSerializable
{
    /**
     * @param array<string, mixed> $config The raw configuration data
     */
    public function __construct(
        private array $config = []
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Get the raw configuration array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Get a configuration value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * Get a string value.
     */
    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    /**
     * Get an integer value.
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get a boolean value.
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        return is_bool($value) ? $value : $default;
    }

    /**
     * Get an array value.
     *
     * @return array<mixed>
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Create a new ActionConfig with an additional value.
     */
    public function with(string $key, mixed $value): self
    {
        return new self(array_merge($this->config, [$key => $value]));
    }

    /**
     * Create a new ActionConfig without a specific key.
     */
    public function without(string $key): self
    {
        $config = $this->config;
        unset($config[$key]);
        return new self($config);
    }

    public function toArray(): array
    {
        return $this->config;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isEmpty(): bool
    {
        return empty($this->config);
    }
}
