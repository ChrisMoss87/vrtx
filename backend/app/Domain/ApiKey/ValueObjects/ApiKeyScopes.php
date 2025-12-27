<?php

declare(strict_types=1);

namespace App\Domain\ApiKey\ValueObjects;

use JsonSerializable;

final readonly class ApiKeyScopes implements JsonSerializable
{
    /**
     * @param array<string> $scopes
     */
    public function __construct(
        private array $scopes = [],
    ) {}

    /**
     * @param array<string> $scopes
     */
    public static function fromArray(array $scopes): self
    {
        return new self(array_values(array_unique(array_filter($scopes))));
    }

    public static function all(): self
    {
        return new self(['*']);
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @return array<string>
     */
    public function toArray(): array
    {
        return $this->scopes;
    }

    /**
     * Check if this scope set includes a specific scope.
     */
    public function has(string $scope): bool
    {
        // Wildcard grants all scopes
        if (in_array('*', $this->scopes, true)) {
            return true;
        }

        // Check for exact match
        if (in_array($scope, $this->scopes, true)) {
            return true;
        }

        // Check for wildcard patterns (e.g., 'read:*' matches 'read:users')
        foreach ($this->scopes as $allowedScope) {
            if (str_ends_with($allowedScope, ':*')) {
                $prefix = substr($allowedScope, 0, -1);
                if (str_starts_with($scope, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if this scope set includes any of the given scopes.
     *
     * @param array<string> $scopes
     */
    public function hasAny(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($this->has($scope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this scope set includes all of the given scopes.
     *
     * @param array<string> $scopes
     */
    public function hasAll(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if (!$this->has($scope)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if this is an unrestricted (wildcard) scope.
     */
    public function isUnrestricted(): bool
    {
        return in_array('*', $this->scopes, true);
    }

    /**
     * Check if no scopes are defined.
     */
    public function isEmpty(): bool
    {
        return empty($this->scopes);
    }

    /**
     * Add a scope and return a new instance.
     */
    public function with(string $scope): self
    {
        if ($this->has($scope)) {
            return $this;
        }

        $scopes = $this->scopes;
        $scopes[] = $scope;

        return new self($scopes);
    }

    /**
     * Remove a scope and return a new instance.
     */
    public function without(string $scope): self
    {
        return new self(array_filter($this->scopes, fn ($s) => $s !== $scope));
    }

    public function jsonSerialize(): array
    {
        return $this->scopes;
    }

    public function __toString(): string
    {
        return implode(', ', $this->scopes);
    }
}
