<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\Shared\Contracts\HasherInterface;
use App\Domain\Shared\Contracts\StringHelperInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\Repositories\SessionRepositoryInterface;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SessionRepositoryInterface $sessionRepository,
        private HasherInterface $hasher,
        private StringHelperInterface $stringHelper,
    ) {}

    /**
     * List users with filtering and pagination.
     */
    public function listUsers(
        ?string $search = null,
        ?string $role = null,
        ?string $status = null,
        int $perPage = 25,
        int $page = 1
    ): PaginatedResult {
        $filters = [];

        if ($search !== null) {
            $filters['search'] = $search;
        }

        if ($role !== null) {
            // Convert role name to role_id if needed
            $filters['role_id'] = is_numeric($role) ? (int) $role : null;
        }

        if ($status !== null && $this->userRepository->hasActiveStatusColumn()) {
            $filters['is_active'] = $status === 'active';
        }

        return $this->userRepository->findWithFilters($filters, $perPage, $page);
    }

    /**
     * Get a single user with roles.
     */
    public function getUser(int $id): ?array
    {
        return $this->userRepository->findByIdWithRoles($id);
    }

    /**
     * Create a new user.
     */
    public function createUser(array $data, ?array $roleIds = null, bool $sendInvite = false): array
    {
        $password = $data['password'] ?? $this->stringHelper->random(16);

        $userData = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $this->hasher->make($password),
        ]);

        if (!empty($roleIds)) {
            $this->userRepository->syncRoles($userData['id'], $roleIds);
        }

        // TODO: If sendInvite is true, send invitation email with password reset link

        return $this->userRepository->findByIdWithRoles($userData['id']);
    }

    /**
     * Update an existing user.
     */
    public function updateUser(int $id, array $data, ?array $roleIds = null): array
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
            $updateData['email_verified_at'] = null;
        }

        if (!empty($updateData)) {
            $this->userRepository->update($id, $updateData);
        }

        if ($roleIds !== null) {
            $this->userRepository->syncRoles($id, $roleIds);
        }

        return $this->userRepository->findByIdWithRoles($id);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(int $id): bool
    {
        return $this->userRepository->delete($id);
    }

    /**
     * Toggle user active status.
     */
    public function toggleUserStatus(int $id): array
    {
        if (!$this->userRepository->hasActiveStatusColumn()) {
            throw new \RuntimeException('User status toggle is not available');
        }

        $isActive = $this->userRepository->toggleActive($id);

        return [
            'id' => $id,
            'is_active' => $isActive,
        ];
    }

    /**
     * Reset user password.
     */
    public function resetPassword(int $id, ?string $newPassword = null, bool $sendEmail = true): array
    {
        if ($newPassword !== null) {
            $this->userRepository->updatePassword($id, $this->hasher->make($newPassword));

            return [
                'message' => 'Password updated successfully',
            ];
        }

        $tempPassword = $this->stringHelper->random(12);
        $this->userRepository->updatePassword($id, $this->hasher->make($tempPassword));

        // TODO: Send password reset email if requested

        return [
            'message' => 'Password reset successfully',
            'temporary_password' => $tempPassword,
        ];
    }

    /**
     * Get user sessions.
     */
    public function getUserSessions(int $id): array
    {
        return $this->sessionRepository->getForUser($id);
    }

    /**
     * Revoke a specific session.
     */
    public function revokeSession(int $userId, string $sessionId): bool
    {
        return $this->sessionRepository->revoke($userId, $sessionId);
    }

    /**
     * Revoke all user sessions.
     */
    public function revokeAllSessions(int $userId): void
    {
        $this->sessionRepository->revokeAll($userId);
        $this->sessionRepository->revokeAllTokens($userId);
    }

    /**
     * Check if user exists.
     */
    public function userExists(int $id): bool
    {
        return $this->userRepository->findById($id) !== null;
    }
}
