<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Domain interface for password hashing.
 *
 * This abstracts the hashing mechanism from the domain layer,
 * allowing domain services to hash passwords without depending on Laravel.
 */
interface HasherInterface
{
    /**
     * Hash a value.
     *
     * @param string $value The value to hash
     * @param array $options Hashing options
     * @return string The hashed value
     */
    public function make(string $value, array $options = []): string;

    /**
     * Check if a value matches a hash.
     *
     * @param string $value The plain value
     * @param string $hashedValue The hashed value
     * @return bool
     */
    public function check(string $value, string $hashedValue): bool;

    /**
     * Check if a hash needs to be rehashed.
     *
     * @param string $hashedValue The hashed value
     * @param array $options Hashing options
     * @return bool
     */
    public function needsRehash(string $hashedValue, array $options = []): bool;
}
