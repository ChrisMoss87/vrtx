<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\Repositories\SessionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SessionRepositoryInterface $sessionRepository,
    ) {}

    /**
     * List users with filtering and pagination.
     */
    public function listUsers(
        ?string $search = null,
        ?string $role = null,
        ?string $status = null,
        int $perPage = 25
    ): LengthAwarePaginator {
        $isActive = null;
        if ($status !== null && $this->userRepository->hasActiveStatusColumn()) {
            $isActive = $status === 'active';
        }

        return $this->userRepository->list($search, $role, $isActive, $perPage);
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
        $password = $data['password'] ?? Str::random(16);

        $userData = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
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
            $this->userRepository->updatePassword($id, Hash::make($newPassword));

            return [
                'message' => 'Password updated successfully',
            ];
        }

        $tempPassword = Str::random(12);
        $this->userRepository->updatePassword($id, Hash::make($tempPassword));

        // TODO: Send password reset email if requested

        return [
            'message' => 'Password reset successfully',
            'temporary_password' => $tempPassword,
        ];
    }

    /**
     * Get user sessions.
     */
    public function getUserSessions(int $id): Collection
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
