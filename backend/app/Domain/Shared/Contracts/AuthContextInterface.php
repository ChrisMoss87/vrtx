<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Interface for authentication context in the domain layer.
 *
 * This abstraction allows domain and application services to access
 * the current authenticated user without coupling to Laravel's Auth facade.
 */
interface AuthContextInterface
{
    /**
     * Get the current authenticated user's ID.
     */
    public function userId(): ?int;

    /**
     * Check if a user is authenticated.
     */
    public function isAuthenticated(): bool;

    /**
     * Get the current user's email.
     */
    public function userEmail(): ?string;

    /**
     * Get the current user's name.
     */
    public function userName(): ?string;
}
