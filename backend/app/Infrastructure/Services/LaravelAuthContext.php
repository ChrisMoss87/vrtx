<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Domain\Shared\Contracts\AuthContextInterface;
use Illuminate\Support\Facades\Auth;

/**
 * Laravel implementation of AuthContextInterface.
 */
final class LaravelAuthContext implements AuthContextInterface
{
    public function userId(): ?int
    {
        return Auth::id();
    }

    public function getUserId(): ?int
    {
        return $this->userId();
    }

    public function isAuthenticated(): bool
    {
        return Auth::check();
    }

    public function userEmail(): ?string
    {
        return Auth::user()?->email;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail();
    }

    public function userName(): ?string
    {
        return Auth::user()?->name;
    }

    public function getUserName(): ?string
    {
        return $this->userName();
    }
}
