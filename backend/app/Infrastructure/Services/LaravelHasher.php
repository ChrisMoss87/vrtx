<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Shared\Contracts\HasherInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Laravel implementation of HasherInterface.
 */
final class LaravelHasher implements HasherInterface
{
    public function make(string $value, array $options = []): string
    {
        return Hash::make($value, $options);
    }

    public function check(string $value, string $hashedValue): bool
    {
        return Hash::check($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return Hash::needsRehash($hashedValue, $options);
    }
}
