<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Shared\Contracts\StringHelperInterface;
use Illuminate\Support\Str;

/**
 * Laravel implementation of StringHelperInterface.
 */
final class LaravelStringHelper implements StringHelperInterface
{
    public function uuid(): string
    {
        return (string) Str::uuid();
    }

    public function random(int $length = 16): string
    {
        return Str::random($length);
    }

    public function snake(string $value, string $delimiter = '_'): string
    {
        return Str::snake($value, $delimiter);
    }

    public function camel(string $value): string
    {
        return Str::camel($value);
    }

    public function studly(string $value): string
    {
        return Str::studly($value);
    }

    public function kebab(string $value): string
    {
        return Str::kebab($value);
    }

    public function slug(string $value, string $separator = '-'): string
    {
        return Str::slug($value, $separator);
    }

    public function singular(string $value): string
    {
        return Str::singular($value);
    }

    public function plural(string $value, int $count = 2): string
    {
        return Str::plural($value, $count);
    }

    public function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        return Str::limit($value, $limit, $end);
    }

    public function contains(string $haystack, string|array $needles): bool
    {
        return Str::contains($haystack, $needles);
    }

    public function startsWith(string $haystack, string|array $needles): bool
    {
        return Str::startsWith($haystack, $needles);
    }

    public function endsWith(string $haystack, string|array $needles): bool
    {
        return Str::endsWith($haystack, $needles);
    }
}
