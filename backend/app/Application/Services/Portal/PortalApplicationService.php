<?php

declare(strict_types=1);

namespace App\Application\Services\Portal;

use App\Domain\Portal\Repositories\PortalUserRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PortalApplicationService
{
    public function __construct(
        private readonly PortalUserRepositoryInterface $repository,
        private readonly AuthContextInterface $authContext
    ) {}

    // =========================================================================
    // QUERY USE CASES - PORTAL USERS
    // =========================================================================

    /**
     * List portal users with filtering and pagination.
     */
    public function listUsers(array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->repository->list($filters, $perPage);
    }

    /**
     * Get a single portal user by ID.
     */
    public function getUser(int $id): ?array
    {
        return $this->repository->findById($id, [
            'accessTokens',
            'activityLogs' => fn($q) => $q->latest()->limit(20),
            'documentShares',
            'notifications' => fn($q) => $q->latest()->limit(10)
        ]);
    }

    /**
     * Get a portal user by email.
     */
    public function getUserByEmail(string $email): ?array
    {
        return $this->repository->findByEmail($email);
    }

    /**
     * Get users by account.
     */
    public function getUsersByAccount(int $accountId): array
    {
        return $this->repository->findByAccountId($accountId);
    }

    /**
     * Get active portal users.
     */
    public function getActiveUsers(): array
    {
        return $this->repository->getActiveUsers();
    }

    /**
     * Get pending portal users.
     */
    public function getPendingUsers(): array
    {
        return $this->repository->getPendingUsers();
    }

    // =========================================================================
    // QUERY USE CASES - INVITATIONS
    // =========================================================================

    /**
     * List invitations with filtering and pagination.
     */
    public function listInvitations(array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->repository->listInvitations($filters, $perPage);
    }

    /**
     * Get pending invitations for an account.
     */
    public function getPendingInvitations(int $accountId): array
    {
        return $this->repository->getPendingInvitations($accountId);
    }

    /**
     * Get invitation by token.
     */
    public function getInvitationByToken(string $token): ?array
    {
        return $this->repository->findInvitationByToken($token);
    }

    // =========================================================================
    // QUERY USE CASES - ACTIVITY LOGS
    // =========================================================================

    /**
     * Get activity logs for a portal user.
     */
    public function getActivityLogs(int $portalUserId, int $limit = 50): array
    {
        return $this->repository->getActivityLogs($portalUserId, $limit);
    }

    /**
     * Get activity logs for an account.
     */
    public function getAccountActivityLogs(int $accountId, int $limit = 100): array
    {
        return $this->repository->getAccountActivityLogs($accountId, $limit);
    }

    /**
     * Get recent logins.
     */
    public function getRecentLogins(int $days = 30): array
    {
        return $this->repository->getRecentLogins($days);
    }

    // =========================================================================
    // QUERY USE CASES - DOCUMENT SHARES
    // =========================================================================

    /**
     * Get document shares for a portal user.
     */
    public function getDocumentShares(int $portalUserId): array
    {
        return $this->repository->getDocumentShares($portalUserId);
    }

    /**
     * Get document shares by type.
     */
    public function getDocumentSharesByType(int $portalUserId, string $documentType): array
    {
        return $this->repository->getDocumentSharesByType($portalUserId, $documentType);
    }

    /**
     * Get documents requiring signature.
     */
    public function getDocumentsRequiringSignature(int $portalUserId): array
    {
        return $this->repository->getDocumentsRequiringSignature($portalUserId);
    }

    // =========================================================================
    // QUERY USE CASES - NOTIFICATIONS
    // =========================================================================

    /**
     * Get notifications for a portal user.
     */
    public function getNotifications(int $portalUserId, int $limit = 50): array
    {
        return $this->repository->getNotifications($portalUserId, $limit);
    }

    /**
     * Get unread notifications.
     */
    public function getUnreadNotifications(int $portalUserId): array
    {
        return $this->repository->getUnreadNotifications($portalUserId);
    }

    /**
     * Get unread notification count.
     */
    public function getUnreadNotificationCount(int $portalUserId): int
    {
        return $this->repository->getUnreadNotificationCount($portalUserId);
    }

    // =========================================================================
    // QUERY USE CASES - ANNOUNCEMENTS
    // =========================================================================

    /**
     * Get active announcements for a portal user.
     */
    public function getActiveAnnouncements(?int $accountId = null): array
    {
        return $this->repository->getActiveAnnouncements($accountId);
    }

    /**
     * List all announcements with filtering.
     */
    public function listAnnouncements(array $filters = [], int $perPage = 25): PaginatedResult
    {
        return $this->repository->listAnnouncements($filters, $perPage);
    }

    // =========================================================================
    // COMMAND USE CASES - PORTAL USERS
    // =========================================================================

    /**
     * Create a portal user.
     */
    public function createUser(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $userData = [
                'email' => $data['email'],
                'password' => $data['password'] ?? null,
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'contact_module' => $data['contact_module'] ?? null,
                'account_id' => $data['account_id'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'verification_token' => $data['verification_token'] ?? bin2hex(random_bytes(32)),
                'preferences' => $data['preferences'] ?? [],
                'timezone' => $data['timezone'] ?? config('app.timezone'),
                'locale' => $data['locale'] ?? config('app.locale'),
            ];

            $user = $this->repository->create($userData);

            // Log creation
            $this->repository->createActivityLog($user['id'], [
                'action' => 'account_created',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $user;
        });
    }

    /**
     * Update a portal user.
     */
    public function updateUser(int $id, array $data): array
    {
        $updateData = array_filter([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'preferences' => $data['preferences'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'locale' => $data['locale'] ?? null,
        ], fn($value) => $value !== null);

        if (!empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        return $this->repository->update($id, $updateData);
    }

    /**
     * Delete a portal user.
     */
    public function deleteUser(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Activate a portal user.
     */
    public function activateUser(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $user = $this->repository->update($id, [
                'status' => 'active',
                'email_verified_at' => now(),
                'verification_token' => null,
            ]);

            $this->repository->createActivityLog($id, [
                'action' => 'account_activated',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $user;
        });
    }

    /**
     * Suspend a portal user.
     */
    public function suspendUser(int $id, ?string $reason = null): array
    {
        return DB::transaction(function () use ($id, $reason) {
            $user = $this->repository->update($id, ['status' => 'suspended']);

            $this->repository->createActivityLog($id, [
                'action' => 'account_suspended',
                'metadata' => ['reason' => $reason],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $user;
        });
    }

    /**
     * Verify email for a portal user.
     */
    public function verifyEmail(string $token): array
    {
        return DB::transaction(function () use ($token) {
            $user = $this->repository->findByVerificationToken($token);

            if (!$user) {
                throw new \InvalidArgumentException('Invalid verification token');
            }

            return $this->repository->update($user['id'], [
                'status' => 'active',
                'email_verified_at' => now(),
                'verification_token' => null,
            ]);
        });
    }

    /**
     * Change password for a portal user.
     */
    public function changePassword(int $id, string $currentPassword, string $newPassword): array
    {
        $user = $this->repository->findById($id);

        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        if (!Hash::check($currentPassword, $user['password'])) {
            throw new \InvalidArgumentException('Current password is incorrect');
        }

        $updatedUser = $this->repository->update($id, ['password' => $newPassword]);

        $this->repository->createActivityLog($id, [
            'action' => 'change_password',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $updatedUser;
    }

    /**
     * Record a login for a portal user.
     */
    public function recordLogin(int $id, string $ipAddress): array
    {
        $user = $this->repository->update($id, [
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);

        $this->repository->createActivityLog($id, [
            'action' => 'login',
            'metadata' => ['ip' => $ipAddress],
            'ip_address' => $ipAddress,
            'user_agent' => request()->userAgent(),
        ]);

        return $user;
    }

    // =========================================================================
    // COMMAND USE CASES - INVITATIONS
    // =========================================================================

    /**
     * Create an invitation.
     */
    public function createInvitation(array $data): array
    {
        $invitationData = [
            'email' => $data['email'],
            'token' => bin2hex(random_bytes(32)),
            'contact_id' => $data['contact_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'role' => $data['role'] ?? 'user',
            'invited_by' => $this->authContext->userId(),
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
        ];

        return $this->repository->createInvitation($invitationData);
    }

    /**
     * Accept an invitation.
     */
    public function acceptInvitation(string $token, array $userData): array
    {
        return DB::transaction(function () use ($token, $userData) {
            $invitation = $this->repository->findInvitationByToken($token);

            if (!$invitation) {
                throw new \InvalidArgumentException('Invalid invitation token');
            }

            // Check if expired
            if ($invitation['expires_at'] && now()->greaterThan($invitation['expires_at'])) {
                throw new \InvalidArgumentException('Invitation has expired');
            }

            // Check if already accepted
            if ($invitation['accepted_at']) {
                throw new \InvalidArgumentException('Invitation has already been accepted');
            }

            // Create portal user
            $user = $this->createUser([
                'email' => $invitation['email'],
                'password' => $userData['password'],
                'name' => $userData['name'],
                'phone' => $userData['phone'] ?? null,
                'contact_id' => $invitation['contact_id'],
                'account_id' => $invitation['account_id'],
                'status' => 'active',
            ]);

            // Mark invitation as accepted
            $this->repository->updateInvitation($invitation['id'], [
                'accepted_at' => now(),
            ]);

            return $user;
        });
    }

    /**
     * Resend an invitation.
     */
    public function resendInvitation(int $id): array
    {
        $invitation = $this->repository->findInvitationByToken(
            $this->repository->findInvitationByToken('')['token'] ?? ''
        );

        if ($invitation && $invitation['accepted_at']) {
            throw new \InvalidArgumentException('Invitation has already been accepted');
        }

        // Update expiration date
        return $this->repository->updateInvitation($id, [
            'expires_at' => now()->addDays(7)
        ]);
    }

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(int $id): bool
    {
        return $this->repository->deleteInvitation($id);
    }

    // =========================================================================
    // COMMAND USE CASES - ACCESS TOKENS
    // =========================================================================

    /**
     * Create an access token for a portal user.
     */
    public function createAccessToken(int $portalUserId, string $name, array $abilities = ['*'], ?\DateTime $expiresAt = null): array
    {
        $plainToken = bin2hex(random_bytes(32));

        $tokenData = [
            'name' => $name,
            'token' => hash('sha256', $plainToken),
            'abilities' => json_encode($abilities),
            'expires_at' => $expiresAt,
        ];

        $token = $this->repository->createAccessToken($portalUserId, $tokenData);
        $token['plain_text_token'] = $plainToken;

        return $token;
    }

    /**
     * Revoke an access token.
     */
    public function revokeAccessToken(int $tokenId): bool
    {
        return $this->repository->deleteAccessToken($tokenId);
    }

    /**
     * Revoke all access tokens for a user.
     */
    public function revokeAllAccessTokens(int $portalUserId): int
    {
        return $this->repository->deleteAllAccessTokens($portalUserId);
    }

    // =========================================================================
    // COMMAND USE CASES - DOCUMENT SHARES
    // =========================================================================

    /**
     * Share a document with a portal user.
     */
    public function shareDocument(int $portalUserId, array $data): array
    {
        return DB::transaction(function () use ($portalUserId, $data) {
            $shareData = [
                'portal_user_id' => $portalUserId,
                'account_id' => $data['account_id'] ?? null,
                'document_type' => $data['document_type'],
                'document_id' => $data['document_id'],
                'can_download' => $data['can_download'] ?? true,
                'requires_signature' => $data['requires_signature'] ?? false,
                'expires_at' => $data['expires_at'] ?? null,
                'shared_by' => $this->authContext->userId(),
            ];

            $share = $this->repository->createDocumentShare($shareData);

            // Notify portal user
            $this->createNotification($portalUserId, [
                'type' => 'document_shared',
                'title' => 'New Document Shared',
                'message' => "A new {$data['document_type']} has been shared with you",
                'action_url' => $data['action_url'] ?? null,
            ]);

            return $share;
        });
    }

    /**
     * Record document view.
     */
    public function recordDocumentView(int $shareId): array
    {
        $share = $this->repository->findDocumentShare($shareId);

        if (!$share) {
            throw new \InvalidArgumentException('Document share not found');
        }

        $updates = ['view_count' => ($share['view_count'] ?? 0) + 1];

        if (!$share['first_viewed_at']) {
            $updates['first_viewed_at'] = now();
        }

        $updates['last_viewed_at'] = now();

        return $this->repository->updateDocumentShare($shareId, $updates);
    }

    /**
     * Sign a document.
     */
    public function signDocument(int $shareId, string $ipAddress): array
    {
        return DB::transaction(function () use ($shareId, $ipAddress) {
            $share = $this->repository->findDocumentShare($shareId);

            if (!$share) {
                throw new \InvalidArgumentException('Document share not found');
            }

            if (!$share['requires_signature']) {
                throw new \InvalidArgumentException('Document does not require signature');
            }

            if ($share['signed_at']) {
                throw new \InvalidArgumentException('Document has already been signed');
            }

            return $this->repository->updateDocumentShare($shareId, [
                'signed_at' => now(),
                'signed_ip' => $ipAddress,
            ]);
        });
    }

    /**
     * Revoke document share.
     */
    public function revokeDocumentShare(int $shareId): bool
    {
        return $this->repository->deleteDocumentShare($shareId);
    }

    // =========================================================================
    // COMMAND USE CASES - NOTIFICATIONS
    // =========================================================================

    /**
     * Create a notification for a portal user.
     */
    public function createNotification(int $portalUserId, array $data): array
    {
        $notificationData = [
            'portal_user_id' => $portalUserId,
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'action_url' => $data['action_url'] ?? null,
            'data' => $data['data'] ?? [],
        ];

        return $this->repository->createNotification($notificationData);
    }

    /**
     * Mark a notification as read.
     */
    public function markNotificationAsRead(int $notificationId): array
    {
        return $this->repository->updateNotification($notificationId, [
            'read_at' => now()
        ]);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllNotificationsAsRead(int $portalUserId): int
    {
        return $this->repository->markAllNotificationsAsRead($portalUserId);
    }

    /**
     * Delete a notification.
     */
    public function deleteNotification(int $notificationId): bool
    {
        return $this->repository->deleteNotification($notificationId);
    }

    // =========================================================================
    // COMMAND USE CASES - ANNOUNCEMENTS
    // =========================================================================

    /**
     * Create an announcement.
     */
    public function createAnnouncement(array $data): array
    {
        $announcementData = [
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type'] ?? 'info',
            'is_active' => $data['is_active'] ?? true,
            'is_dismissible' => $data['is_dismissible'] ?? true,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'target_accounts' => $data['target_accounts'] ?? [],
            'created_by' => $this->authContext->userId(),
        ];

        return $this->repository->createAnnouncement($announcementData);
    }

    /**
     * Update an announcement.
     */
    public function updateAnnouncement(int $id, array $data): array
    {
        $updateData = array_filter([
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'type' => $data['type'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'is_dismissible' => $data['is_dismissible'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'target_accounts' => $data['target_accounts'] ?? null,
        ], fn($value) => $value !== null);

        return $this->repository->updateAnnouncement($id, $updateData);
    }

    /**
     * Delete an announcement.
     */
    public function deleteAnnouncement(int $id): bool
    {
        return $this->repository->deleteAnnouncement($id);
    }

    // =========================================================================
    // ANALYTICS & REPORTING
    // =========================================================================

    /**
     * Get portal user statistics.
     */
    public function getUserStatistics(): array
    {
        return $this->repository->getUserStatistics();
    }

    /**
     * Get document share statistics.
     */
    public function getDocumentShareStatistics(?int $accountId = null): array
    {
        return $this->repository->getDocumentShareStatistics($accountId);
    }

    /**
     * Get activity summary for a portal user.
     */
    public function getUserActivitySummary(int $portalUserId, int $days = 30): array
    {
        return $this->repository->getUserActivitySummary($portalUserId, $days);
    }

    /**
     * Get invitation statistics.
     */
    public function getInvitationStatistics(?int $accountId = null): array
    {
        return $this->repository->getInvitationStatistics($accountId);
    }
}
