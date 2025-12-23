<?php

declare(strict_types=1);

namespace App\Domain\Portal\Repositories;

use App\Domain\Portal\Entities\PortalUser;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface PortalUserRepositoryInterface
{
    // =========================================================================
    // BASIC CRUD OPERATIONS
    // =========================================================================

    /**
     * Find a portal user by ID.
     */
    public function findById(int $id): ?PortalUser;

    /**
     * Find a portal user by ID with optional relations (returns array).
     */
    public function findByIdAsArray(int $id, array $with = []): ?array;

    /**
     * Find a portal user by email.
     */
    public function findByEmail(string $email): ?array;

    /**
     * Find a portal user by verification token.
     */
    public function findByVerificationToken(string $token): ?array;

    /**
     * Save a portal user entity.
     */
    public function save(PortalUser $entity): PortalUser;

    /**
     * Create a new portal user.
     */
    public function create(array $data): array;

    /**
     * Update a portal user.
     */
    public function update(int $id, array $data): array;

    /**
     * Delete a portal user.
     */
    public function delete(int $id): bool;

    // =========================================================================
    // QUERY OPERATIONS - LISTS & COLLECTIONS
    // =========================================================================

    /**
     * List portal users with filtering and pagination.
     */
    public function list(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Get users by account ID.
     */
    public function findByAccountId(int $accountId, string $orderBy = 'created_at', string $orderDir = 'desc'): array;

    /**
     * Get users by status.
     */
    public function findByStatus(string $status, string $orderBy = 'created_at', string $orderDir = 'desc'): array;

    /**
     * Get active users.
     */
    public function getActiveUsers(string $orderBy = 'last_login_at', string $orderDir = 'desc'): array;

    /**
     * Get pending users.
     */
    public function getPendingUsers(string $orderBy = 'created_at', string $orderDir = 'desc'): array;

    /**
     * Get users with unverified emails.
     */
    public function getUnverifiedUsers(): array;

    // =========================================================================
    // INVITATIONS
    // =========================================================================

    /**
     * List invitations with filtering and pagination.
     */
    public function listInvitations(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Find invitation by token.
     */
    public function findInvitationByToken(string $token): ?array;

    /**
     * Get pending invitations for an account.
     */
    public function getPendingInvitations(int $accountId): array;

    /**
     * Create invitation.
     */
    public function createInvitation(array $data): array;

    /**
     * Update invitation.
     */
    public function updateInvitation(int $id, array $data): array;

    /**
     * Delete invitation.
     */
    public function deleteInvitation(int $id): bool;

    // =========================================================================
    // ACTIVITY LOGS
    // =========================================================================

    /**
     * Get activity logs for a portal user.
     */
    public function getActivityLogs(int $portalUserId, int $limit = 50): array;

    /**
     * Get activity logs for an account.
     */
    public function getAccountActivityLogs(int $accountId, int $limit = 100): array;

    /**
     * Get recent logins.
     */
    public function getRecentLogins(int $days = 30): array;

    /**
     * Create activity log.
     */
    public function createActivityLog(int $portalUserId, array $data): array;

    // =========================================================================
    // DOCUMENT SHARES
    // =========================================================================

    /**
     * Get document shares for a portal user.
     */
    public function getDocumentShares(int $portalUserId): array;

    /**
     * Get document shares by type.
     */
    public function getDocumentSharesByType(int $portalUserId, string $documentType): array;

    /**
     * Get documents requiring signature.
     */
    public function getDocumentsRequiringSignature(int $portalUserId): array;

    /**
     * Find document share by ID.
     */
    public function findDocumentShare(int $id): ?array;

    /**
     * Create document share.
     */
    public function createDocumentShare(array $data): array;

    /**
     * Update document share.
     */
    public function updateDocumentShare(int $id, array $data): array;

    /**
     * Delete document share.
     */
    public function deleteDocumentShare(int $id): bool;

    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================

    /**
     * Get notifications for a portal user.
     */
    public function getNotifications(int $portalUserId, int $limit = 50): array;

    /**
     * Get unread notifications.
     */
    public function getUnreadNotifications(int $portalUserId): array;

    /**
     * Get unread notification count.
     */
    public function getUnreadNotificationCount(int $portalUserId): int;

    /**
     * Find notification by ID.
     */
    public function findNotification(int $id): ?array;

    /**
     * Create notification.
     */
    public function createNotification(array $data): array;

    /**
     * Update notification.
     */
    public function updateNotification(int $id, array $data): array;

    /**
     * Delete notification.
     */
    public function deleteNotification(int $id): bool;

    /**
     * Mark all notifications as read.
     */
    public function markAllNotificationsAsRead(int $portalUserId): int;

    // =========================================================================
    // ANNOUNCEMENTS
    // =========================================================================

    /**
     * Get active announcements.
     */
    public function getActiveAnnouncements(?int $accountId = null): array;

    /**
     * List announcements with filtering and pagination.
     */
    public function listAnnouncements(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult;

    /**
     * Find announcement by ID.
     */
    public function findAnnouncement(int $id): ?array;

    /**
     * Create announcement.
     */
    public function createAnnouncement(array $data): array;

    /**
     * Update announcement.
     */
    public function updateAnnouncement(int $id, array $data): array;

    /**
     * Delete announcement.
     */
    public function deleteAnnouncement(int $id): bool;

    // =========================================================================
    // ACCESS TOKENS
    // =========================================================================

    /**
     * Create access token.
     */
    public function createAccessToken(int $portalUserId, array $data): array;

    /**
     * Delete access token.
     */
    public function deleteAccessToken(int $tokenId): bool;

    /**
     * Delete all access tokens for a user.
     */
    public function deleteAllAccessTokens(int $portalUserId): int;

    // =========================================================================
    // STATISTICS & ANALYTICS
    // =========================================================================

    /**
     * Get portal user statistics.
     */
    public function getUserStatistics(): array;

    /**
     * Get document share statistics.
     */
    public function getDocumentShareStatistics(?int $accountId = null): array;

    /**
     * Get user activity summary.
     */
    public function getUserActivitySummary(int $portalUserId, int $days = 30): array;

    /**
     * Get invitation statistics.
     */
    public function getInvitationStatistics(?int $accountId = null): array;
}
