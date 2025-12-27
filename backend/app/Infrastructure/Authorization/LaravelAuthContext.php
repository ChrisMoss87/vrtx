<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization;

use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\User\Entities\User as DomainUser;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\ValueObjects\UserId;
use Illuminate\Support\Facades\Auth;

/**
 * Laravel implementation of AuthContextInterface.
 * Bridges Laravel's authentication with the domain layer.
 */
class LaravelAuthContext implements AuthContextInterface
{
    private ?int $overrideUserId = null;

    private ?DomainUser $currentUser = null;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Get the current authenticated user's ID.
     */
    public function userId(): ?int
    {
        if ($this->overrideUserId !== null) {
            return $this->overrideUserId;
        }

        $user = Auth::user();

        return $user?->id;
    }

    /**
     * Alias for userId() - for compatibility.
     */
    public function getUserId(): ?int
    {
        return $this->userId();
    }

    /**
     * Check if a user is authenticated.
     */
    public function isAuthenticated(): bool
    {
        return $this->userId() !== null;
    }

    /**
     * Get the current user's email.
     */
    public function userEmail(): ?string
    {
        $user = Auth::user();

        return $user?->email;
    }

    /**
     * Get the current user's name.
     */
    public function userName(): ?string
    {
        $user = Auth::user();

        return $user?->name;
    }

    /**
     * Alias for userEmail() - for compatibility.
     */
    public function getUserEmail(): ?string
    {
        return $this->userEmail();
    }

    /**
     * Alias for userName() - for compatibility.
     */
    public function getUserName(): ?string
    {
        return $this->userName();
    }

    // ========== ADDITIONAL METHODS (not in interface) ==========

    /**
     * Get the current authenticated user as a domain entity.
     */
    public function getCurrentUser(): ?DomainUser
    {
        if ($this->currentUser !== null) {
            return $this->currentUser;
        }

        $userId = $this->userId();

        if ($userId === null) {
            return null;
        }

        $this->currentUser = $this->userRepository->findEntityById(UserId::fromInt($userId));

        return $this->currentUser;
    }

    /**
     * Check if a user is a guest (not authenticated).
     */
    public function isGuest(): bool
    {
        return !$this->isAuthenticated();
    }

    /**
     * Set the current user context (useful for testing or queue jobs).
     */
    public function setUserId(?int $userId): void
    {
        $this->overrideUserId = $userId;
        $this->currentUser = null; // Reset cached user
    }

    /**
     * Clear the current user context.
     */
    public function clearContext(): void
    {
        $this->overrideUserId = null;
        $this->currentUser = null;
    }
}
