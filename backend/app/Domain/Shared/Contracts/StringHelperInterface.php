<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Domain interface for string utilities.
 *
 * This abstracts string manipulation from the domain layer,
 * providing common string operations without depending on Laravel's Str helper.
 */
interface StringHelperInterface
{
    /**
     * Generate a UUID v4.
     */
    public function uuid(): string;

    /**
     * Generate a random string.
     */
    public function random(int $length = 16): string;

    /**
     * Convert a string to snake_case.
     */
    public function snake(string $value, string $delimiter = '_'): string;

    /**
     * Convert a string to camelCase.
     */
    public function camel(string $value): string;

    /**
     * Convert a string to StudlyCase.
     */
    public function studly(string $value): string;

    /**
     * Convert a string to kebab-case.
     */
    public function kebab(string $value): string;

    /**
     * Convert a string to slug format.
     */
    public function slug(string $value, string $separator = '-'): string;

    /**
     * Get the singular form of a word.
     */
    public function singular(string $value): string;

    /**
     * Get the plural form of a word.
     */
    public function plural(string $value, int $count = 2): string;

    /**
     * Limit the number of characters in a string.
     */
    public function limit(string $value, int $limit = 100, string $end = '...'): string;

    /**
     * Determine if a string contains a given substring.
     */
    public function contains(string $haystack, string|array $needles): bool;

    /**
     * Determine if a string starts with a given substring.
     */
    public function startsWith(string $haystack, string|array $needles): bool;

    /**
     * Determine if a string ends with a given substring.
     */
    public function endsWith(string $haystack, string|array $needles): bool;
}
