<?php

declare(strict_types=1);

namespace App\Domain\ApiKey\ValueObjects;

use InvalidArgumentException;

final readonly class ApiKeyHash
{
    private const HASH_LENGTH = 64; // SHA-256 hex length

    public function __construct(
        private string $value,
    ) {
        if (empty($value)) {
            throw new InvalidArgumentException('API Key hash cannot be empty');
        }
    }

    /**
     * Create a hash from a plain text API key.
     */
    public static function fromPlainKey(string $plainKey): self
    {
        return new self(hash('sha256', $plainKey));
    }

    /**
     * Create from an already hashed value (from database).
     */
    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    /**
     * Generate a new random API key and return both the plain key and hash.
     *
     * @return array{plain: string, hash: self}
     */
    public static function generate(): array
    {
        $plainKey = bin2hex(random_bytes(32));

        return [
            'plain' => $plainKey,
            'hash' => self::fromPlainKey($plainKey),
        ];
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Verify that a plain text key matches this hash.
     */
    public function verify(string $plainKey): bool
    {
        return hash_equals($this->value, hash('sha256', $plainKey));
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
