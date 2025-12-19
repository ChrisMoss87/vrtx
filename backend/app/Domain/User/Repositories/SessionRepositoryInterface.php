<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use Illuminate\Support\Collection;

/**
 * Repository interface for user sessions.
 */
interface SessionRepositoryInterface
{
    /**
     * Get all sessions for a user.
     *
     * @return Collection<int, array>
     */
    public function getForUser(int $userId): Collection;

    /**
     * Revoke a specific session.
     */
    public function revoke(int $userId, string $sessionId): bool;

    /**
     * Revoke all sessions for a user.
     */
    public function revokeAll(int $userId): int;

    /**
     * Revoke all API tokens for a user.
     */
    public function revokeAllTokens(int $userId): int;
}
