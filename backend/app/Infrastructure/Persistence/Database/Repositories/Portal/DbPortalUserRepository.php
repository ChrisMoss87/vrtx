<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Portal;

use App\Domain\Portal\Entities\PortalUser as PortalUserEntity;
use App\Domain\Portal\Repositories\PortalUserRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbPortalUserRepository implements PortalUserRepositoryInterface
{
    private const TABLE = 'portal_users';
    private const TABLE_INVITATIONS = 'portal_invitations';
    private const TABLE_ACTIVITY_LOGS = 'portal_activity_logs';
    private const TABLE_DOCUMENT_SHARES = 'portal_document_shares';
    private const TABLE_NOTIFICATIONS = 'portal_notifications';
    private const TABLE_ANNOUNCEMENTS = 'portal_announcements';
    private const TABLE_ACCESS_TOKENS = 'portal_access_tokens';
    private const TABLE_USERS = 'users';

    // Status constants
    private const STATUS_ACTIVE = 'active';
    private const STATUS_PENDING = 'pending';
    private const STATUS_SUSPENDED = 'suspended';
    // =========================================================================
    // BASIC CRUD OPERATIONS
    // =========================================================================

    public function findById(int $id): ?PortalUserEntity
    {
        $user = DB::table(self::TABLE)->where('id', $id)->first();
        return $user ? $this->toDomainEntity($user) : null;
    }

    public function findByIdAsArray(int $id, array $with = []): ?array
    {
        $user = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$user) {
            return null;
        }

        $result = $this->toArray($user);

        // Handle relationships manually if requested
        if (!empty($with)) {
            // Note: With Query Builder, we need to load relationships manually
            // This is simplified - add specific relationship loading as needed
        }

        return $result;
    }

    public function findByEmail(string $email): ?array
    {
        $user = DB::table(self::TABLE)->where('email', $email)->first();
        return $user ? $this->toArray($user) : null;
    }

    public function findByVerificationToken(string $token): ?array
    {
        $user = DB::table(self::TABLE)->where('verification_token', $token)->first();
        return $user ? $this->toArray($user) : null;
    }

    public function save(PortalUserEntity $entity): PortalUserEntity
    {
        $data = $this->toModelData($entity);

        if ($entity->getId()) {
            $existing = DB::table(self::TABLE)->where('id', $entity->getId())->first();
            if (!$existing) {
                throw new \RuntimeException("PortalUser with ID {$entity->getId()} not found");
            }

            $data['updated_at'] = now();
            DB::table(self::TABLE)->where('id', $entity->getId())->update($data);
            $user = DB::table(self::TABLE)->where('id', $entity->getId())->first();
        } else {
            $data['created_at'] = now();
            $data['updated_at'] = now();
            $id = DB::table(self::TABLE)->insertGetId($data);
            $user = DB::table(self::TABLE)->where('id', $id)->first();
        }

        return $this->toDomainEntity($user);
    }

    public function create(array $data): array
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE)->insertGetId($data);
        $user = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->toArray($user);
    }

    public function update(int $id, array $data): array
    {
        $existing = DB::table(self::TABLE)->where('id', $id)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalUser with ID {$id} not found");
        }

        $data['updated_at'] = now();
        DB::table(self::TABLE)->where('id', $id)->update($data);

        $user = DB::table(self::TABLE)->where('id', $id)->first();
        return $this->toArray($user);
    }

    public function delete(int $id): bool
    {
        $existing = DB::table(self::TABLE)->where('id', $id)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalUser with ID {$id} not found");
        }

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // QUERY OPERATIONS - LISTS & COLLECTIONS
    // =========================================================================

    public function list(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by account
        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        // Filter by contact module
        if (!empty($filters['contact_module'])) {
            $query->where('contact_module', $filters['contact_module']);
        }

        // Filter by email verified
        if (isset($filters['email_verified'])) {
            if ($filters['email_verified']) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Manual pagination
        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $items = $query->offset($offset)
            ->limit($perPage)
            ->get();

        return PaginatedResult::create(
            items: array_map(fn($item) => $this->toArray($item), $items->all()),
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findByAccountId(int $accountId, string $orderBy = 'created_at', string $orderDir = 'desc'): array
    {
        $results = DB::table(self::TABLE)
            ->where('account_id', $accountId)
            ->orderBy($orderBy, $orderDir)
            ->get();

        return array_map(fn($user) => $this->toArray($user), $results->all());
    }

    public function findByStatus(string $status, string $orderBy = 'created_at', string $orderDir = 'desc'): array
    {
        $results = DB::table(self::TABLE)
            ->where('status', $status)
            ->orderBy($orderBy, $orderDir)
            ->get();

        return array_map(fn($user) => $this->toArray($user), $results->all());
    }

    public function getActiveUsers(string $orderBy = 'last_login_at', string $orderDir = 'desc'): array
    {
        $results = DB::table(self::TABLE)
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy($orderBy, $orderDir)
            ->get();

        return array_map(fn($user) => $this->toArray($user), $results->all());
    }

    public function getPendingUsers(string $orderBy = 'created_at', string $orderDir = 'desc'): array
    {
        $results = DB::table(self::TABLE)
            ->where('status', self::STATUS_PENDING)
            ->orderBy($orderBy, $orderDir)
            ->get();

        return array_map(fn($user) => $this->toArray($user), $results->all());
    }

    public function getUnverifiedUsers(): array
    {
        $results = DB::table(self::TABLE)
            ->whereNull('email_verified_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return array_map(fn($user) => $this->toArray($user), $results->all());
    }

    // =========================================================================
    // INVITATIONS
    // =========================================================================

    public function listInvitations(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_INVITATIONS);

        // Filter by account
        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'pending':
                    $query->whereNull('accepted_at')
                        ->where('expires_at', '>', now());
                    break;
                case 'accepted':
                    $query->whereNotNull('accepted_at');
                    break;
                case 'expired':
                    $query->whereNull('accepted_at')
                        ->where('expires_at', '<=', now());
                    break;
            }
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Manual pagination
        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $items = $query->offset($offset)
            ->limit($perPage)
            ->get();

        // Load inviter relationship manually
        $results = array_map(function ($item) {
            $arr = $this->toArray($item);
            if (isset($item->inviter_id)) {
                $inviter = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $item->inviter_id)
                    ->first();
                $arr['inviter'] = $inviter ? $this->toArray($inviter) : null;
            }
            return $arr;
        }, $items->all());

        return PaginatedResult::create(
            items: $results,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findInvitationByToken(string $token): ?array
    {
        $invitation = DB::table(self::TABLE_INVITATIONS)->where('token', $token)->first();
        return $invitation ? $this->toArray($invitation) : null;
    }

    public function getPendingInvitations(int $accountId): array
    {
        $results = DB::table(self::TABLE_INVITATIONS)
            ->where('account_id', $accountId)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        return array_map(fn($invitation) => $this->toArray($invitation), $results->all());
    }

    public function createInvitation(array $data): array
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_INVITATIONS)->insertGetId($data);
        $invitation = DB::table(self::TABLE_INVITATIONS)->where('id', $id)->first();

        return $this->toArray($invitation);
    }

    public function updateInvitation(int $id, array $data): array
    {
        $existing = DB::table(self::TABLE_INVITATIONS)->where('id', $id)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalInvitation with ID {$id} not found");
        }

        $data['updated_at'] = now();
        DB::table(self::TABLE_INVITATIONS)->where('id', $id)->update($data);

        $invitation = DB::table(self::TABLE_INVITATIONS)->where('id', $id)->first();
        return $this->toArray($invitation);
    }

    public function deleteInvitation(int $id): bool
    {
        $existing = DB::table(self::TABLE_INVITATIONS)->where('id', $id)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalInvitation with ID {$id} not found");
        }

        return DB::table(self::TABLE_INVITATIONS)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // ACTIVITY LOGS
    // =========================================================================

    public function getActivityLogs(int $portalUserId, int $limit = 50): array
    {
        $results = DB::table(self::TABLE_ACTIVITY_LOGS)
            ->where('portal_user_id', $portalUserId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(fn($log) => $this->toArray($log), $results->all());
    }

    public function getAccountActivityLogs(int $accountId, int $limit = 100): array
    {
        $results = DB::table(self::TABLE_ACTIVITY_LOGS)
            ->join(self::TABLE, self::TABLE_ACTIVITY_LOGS . '.portal_user_id', '=', self::TABLE . '.id')
            ->where(self::TABLE . '.account_id', $accountId)
            ->select(self::TABLE_ACTIVITY_LOGS . '.*')
            ->orderBy(self::TABLE_ACTIVITY_LOGS . '.created_at', 'desc')
            ->limit($limit)
            ->get();

        // Load portal user relationship manually
        return array_map(function ($log) {
            $arr = $this->toArray($log);
            if (isset($log->portal_user_id)) {
                $user = DB::table(self::TABLE)
                    ->select('id', 'name', 'email')
                    ->where('id', $log->portal_user_id)
                    ->first();
                $arr['portal_user'] = $user ? $this->toArray($user) : null;
            }
            return $arr;
        }, $results->all());
    }

    public function getRecentLogins(int $days = 30): array
    {
        $results = DB::table(self::TABLE_ACTIVITY_LOGS)
            ->where('action', 'login')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();

        // Load portal user relationship manually
        return array_map(function ($log) {
            $arr = $this->toArray($log);
            if (isset($log->portal_user_id)) {
                $user = DB::table(self::TABLE)
                    ->select('id', 'name', 'email')
                    ->where('id', $log->portal_user_id)
                    ->first();
                $arr['portal_user'] = $user ? $this->toArray($user) : null;
            }
            return $arr;
        }, $results->all());
    }

    public function createActivityLog(int $portalUserId, array $data): array
    {
        $data['portal_user_id'] = $portalUserId;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_ACTIVITY_LOGS)->insertGetId($data);
        $log = DB::table(self::TABLE_ACTIVITY_LOGS)->where('id', $id)->first();

        return $this->toArray($log);
    }

    // =========================================================================
    // DOCUMENT SHARES
    // =========================================================================

    public function getDocumentShares(int $portalUserId): array
    {
        $results = DB::table(self::TABLE_DOCUMENT_SHARES)
            ->where('portal_user_id', $portalUserId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Load sharer relationship manually
        return array_map(function ($share) {
            $arr = $this->toArray($share);
            if (isset($share->sharer_id)) {
                $sharer = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $share->sharer_id)
                    ->first();
                $arr['sharer'] = $sharer ? $this->toArray($sharer) : null;
            }
            return $arr;
        }, $results->all());
    }

    public function getDocumentSharesByType(int $portalUserId, string $documentType): array
    {
        $results = DB::table(self::TABLE_DOCUMENT_SHARES)
            ->where('portal_user_id', $portalUserId)
            ->where('document_type', $documentType)
            ->orderBy('created_at', 'desc')
            ->get();

        // Load sharer relationship manually
        return array_map(function ($share) {
            $arr = $this->toArray($share);
            if (isset($share->sharer_id)) {
                $sharer = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $share->sharer_id)
                    ->first();
                $arr['sharer'] = $sharer ? $this->toArray($sharer) : null;
            }
            return $arr;
        }, $results->all());
    }

    public function getDocumentsRequiringSignature(int $portalUserId): array
    {
        $results = DB::table(self::TABLE_DOCUMENT_SHARES)
            ->where('portal_user_id', $portalUserId)
            ->where('requires_signature', true)
            ->whereNull('signed_at')
            ->orderBy('created_at', 'desc')
            ->get();

        // Load sharer relationship manually
        return array_map(function ($share) {
            $arr = $this->toArray($share);
            if (isset($share->sharer_id)) {
                $sharer = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $share->sharer_id)
                    ->first();
                $arr['sharer'] = $sharer ? $this->toArray($sharer) : null;
            }
            return $arr;
        }, $results->all());
    }

    public function findDocumentShare(int $id): ?array
    {
        $share = DB::table(self::TABLE_DOCUMENT_SHARES)->where('id', $id)->first();
        return $share ? $this->toArray($share) : null;
    }

    public function createDocumentShare(array $data): array
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_DOCUMENT_SHARES)->insertGetId($data);
        $share = DB::table(self::TABLE_DOCUMENT_SHARES)->where('id', $id)->first();

        return $this->toArray($share);
    }

    public function updateDocumentShare(int $id, array $data): array
    {
        $existing = DB::table(self::TABLE_DOCUMENT_SHARES)->where('id', $id)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalDocumentShare with ID {$id} not found");
        }

        $data['updated_at'] = now();
        DB::table(self::TABLE_DOCUMENT_SHARES)->where('id', $id)->update($data);

        $share = DB::table(self::TABLE_DOCUMENT_SHARES)->where('id', $id)->first();
        return $this->toArray($share);
    }

    public function deleteDocumentShare(int $id): bool
    {
        $existing = DB::table(self::TABLE_DOCUMENT_SHARES)->where('id', $id)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalDocumentShare with ID {$id} not found");
        }

        return DB::table(self::TABLE_DOCUMENT_SHARES)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================

    public function getNotifications(int $portalUserId, int $limit = 50): array
    {
        $results = DB::table(self::TABLE_NOTIFICATIONS)
            ->where('portal_user_id', $portalUserId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return array_map(fn($notification) => $this->toArray($notification), $results->all());
    }

    public function getUnreadNotifications(int $portalUserId): array
    {
        $results = DB::table(self::TABLE_NOTIFICATIONS)
            ->where('portal_user_id', $portalUserId)
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return array_map(fn($notification) => $this->toArray($notification), $results->all());
    }

    public function getUnreadNotificationCount(int $portalUserId): int
    {
        return DB::table(self::TABLE_NOTIFICATIONS)
            ->where('portal_user_id', $portalUserId)
            ->whereNull('read_at')
            ->count();
    }

    public function findNotification(int $id): ?array
    {
        $notification = DB::table(self::TABLE_NOTIFICATIONS)->where('id', $id)->first();
        return $notification ? $this->toArray($notification) : null;
    }

    public function createNotification(array $data): array
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_NOTIFICATIONS)->insertGetId($data);
        $notification = DB::table(self::TABLE_NOTIFICATIONS)->where('id', $id)->first();

        return $this->toArray($notification);
    }

    public function updateNotification(int $id, array $data): array
    {
        $existing = DB::table(self::TABLE_NOTIFICATIONS)->where('id', $id)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalNotification with ID {$id} not found");
        }

        $data['updated_at'] = now();
        DB::table(self::TABLE_NOTIFICATIONS)->where('id', $id)->update($data);

        $notification = DB::table(self::TABLE_NOTIFICATIONS)->where('id', $id)->first();
        return $this->toArray($notification);
    }

    public function deleteNotification(int $id): bool
    {
        $existing = DB::table(self::TABLE_NOTIFICATIONS)->where('id', $id)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalNotification with ID {$id} not found");
        }

        return DB::table(self::TABLE_NOTIFICATIONS)->where('id', $id)->delete() > 0;
    }

    public function markAllNotificationsAsRead(int $portalUserId): int
    {
        return DB::table(self::TABLE_NOTIFICATIONS)
            ->where('portal_user_id', $portalUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    // =========================================================================
    // ANNOUNCEMENTS
    // =========================================================================

    public function getActiveAnnouncements(?int $accountId = null): array
    {
        $query = DB::table(self::TABLE_ANNOUNCEMENTS)
            ->where('is_active', true);

        if ($accountId) {
            $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)
                    ->orWhereNull('account_id');
            });
        }

        $results = $query->orderBy('created_at', 'desc')->get();

        return array_map(fn($announcement) => $this->toArray($announcement), $results->all());
    }

    public function listAnnouncements(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_ANNOUNCEMENTS);

        // Filter by active
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Manual pagination
        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $items = $query->offset($offset)
            ->limit($perPage)
            ->get();

        // Load creator relationship manually
        $results = array_map(function ($item) {
            $arr = $this->toArray($item);
            if (isset($item->creator_id)) {
                $creator = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $item->creator_id)
                    ->first();
                $arr['creator'] = $creator ? $this->toArray($creator) : null;
            }
            return $arr;
        }, $items->all());

        return PaginatedResult::create(
            items: $results,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findAnnouncement(int $id): ?array
    {
        $announcement = DB::table(self::TABLE_ANNOUNCEMENTS)->where('id', $id)->first();
        return $announcement ? $this->toArray($announcement) : null;
    }

    public function createAnnouncement(array $data): array
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_ANNOUNCEMENTS)->insertGetId($data);
        $announcement = DB::table(self::TABLE_ANNOUNCEMENTS)->where('id', $id)->first();

        return $this->toArray($announcement);
    }

    public function updateAnnouncement(int $id, array $data): array
    {
        $existing = DB::table(self::TABLE_ANNOUNCEMENTS)->where('id', $id)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalAnnouncement with ID {$id} not found");
        }

        $data['updated_at'] = now();
        DB::table(self::TABLE_ANNOUNCEMENTS)->where('id', $id)->update($data);

        $announcement = DB::table(self::TABLE_ANNOUNCEMENTS)->where('id', $id)->first();
        return $this->toArray($announcement);
    }

    public function deleteAnnouncement(int $id): bool
    {
        $existing = DB::table(self::TABLE_ANNOUNCEMENTS)->where('id', $id)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalAnnouncement with ID {$id} not found");
        }

        return DB::table(self::TABLE_ANNOUNCEMENTS)->where('id', $id)->delete() > 0;
    }

    // =========================================================================
    // ACCESS TOKENS
    // =========================================================================

    public function createAccessToken(int $portalUserId, array $data): array
    {
        $data['portal_user_id'] = $portalUserId;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table(self::TABLE_ACCESS_TOKENS)->insertGetId($data);
        $token = DB::table(self::TABLE_ACCESS_TOKENS)->where('id', $id)->first();

        return $this->toArray($token);
    }

    public function deleteAccessToken(int $tokenId): bool
    {
        $existing = DB::table(self::TABLE_ACCESS_TOKENS)->where('id', $tokenId)->first();
        if (!$existing) {
            throw new \RuntimeException("PortalAccessToken with ID {$tokenId} not found");
        }

        return DB::table(self::TABLE_ACCESS_TOKENS)->where('id', $tokenId)->delete() > 0;
    }

    public function deleteAllAccessTokens(int $portalUserId): int
    {
        return DB::table(self::TABLE_ACCESS_TOKENS)
            ->where('portal_user_id', $portalUserId)
            ->delete();
    }

    // =========================================================================
    // STATISTICS & ANALYTICS
    // =========================================================================

    public function getUserStatistics(): array
    {
        $totalUsers = DB::table(self::TABLE)->count();
        $activeUsers = DB::table(self::TABLE)->where('status', self::STATUS_ACTIVE)->count();
        $pendingUsers = DB::table(self::TABLE)->where('status', self::STATUS_PENDING)->count();
        $suspendedUsers = DB::table(self::TABLE)->where('status', self::STATUS_SUSPENDED)->count();

        $recentLogins = DB::table(self::TABLE)
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', now()->subDays(30))
            ->count();

        $emailVerified = DB::table(self::TABLE)->whereNotNull('email_verified_at')->count();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'pending_users' => $pendingUsers,
            'suspended_users' => $suspendedUsers,
            'recent_logins_30d' => $recentLogins,
            'email_verified_count' => $emailVerified,
            'email_verified_rate' => $totalUsers > 0
                ? round(($emailVerified / $totalUsers) * 100, 2)
                : 0,
        ];
    }

    public function getDocumentShareStatistics(?int $accountId = null): array
    {
        $query = DB::table(self::TABLE_DOCUMENT_SHARES);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $totalShares = $query->count();

        $requiresSignature = DB::table(self::TABLE_DOCUMENT_SHARES)
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->where('requires_signature', true)
            ->count();

        $signed = DB::table(self::TABLE_DOCUMENT_SHARES)
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->whereNotNull('signed_at')
            ->count();

        $pendingSignature = DB::table(self::TABLE_DOCUMENT_SHARES)
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->where('requires_signature', true)
            ->whereNull('signed_at')
            ->count();

        $sharesByTypeQuery = DB::table(self::TABLE_DOCUMENT_SHARES)
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->selectRaw('document_type, COUNT(*) as count')
            ->groupBy('document_type')
            ->get();

        $sharesByType = [];
        foreach ($sharesByTypeQuery as $row) {
            $sharesByType[$row->document_type] = $row->count;
        }

        return [
            'total_shares' => $totalShares,
            'requires_signature' => $requiresSignature,
            'signed' => $signed,
            'pending_signature' => $pendingSignature,
            'shares_by_type' => $sharesByType,
        ];
    }

    public function getUserActivitySummary(int $portalUserId, int $days = 30): array
    {
        $user = DB::table(self::TABLE)->where('id', $portalUserId)->first();
        if (!$user) {
            throw new \RuntimeException("PortalUser with ID {$portalUserId} not found");
        }

        $activityCount = DB::table(self::TABLE_ACTIVITY_LOGS)
            ->where('portal_user_id', $portalUserId)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        $activityByTypeQuery = DB::table(self::TABLE_ACTIVITY_LOGS)
            ->where('portal_user_id', $portalUserId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->get();

        $activityByType = [];
        foreach ($activityByTypeQuery as $row) {
            $activityByType[$row->action] = $row->count;
        }

        $documentsViewed = DB::table(self::TABLE_DOCUMENT_SHARES)
            ->where('portal_user_id', $portalUserId)
            ->whereNotNull('first_viewed_at')
            ->count();

        $documentsSigned = DB::table(self::TABLE_DOCUMENT_SHARES)
            ->where('portal_user_id', $portalUserId)
            ->whereNotNull('signed_at')
            ->count();

        return [
            'user_id' => $portalUserId,
            'period_days' => $days,
            'total_activities' => $activityCount,
            'activities_by_type' => $activityByType,
            'documents_viewed' => $documentsViewed,
            'documents_signed' => $documentsSigned,
            'last_login' => $user->last_login_at ?? null,
        ];
    }

    public function getInvitationStatistics(?int $accountId = null): array
    {
        $query = DB::table(self::TABLE_INVITATIONS);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $totalInvitations = $query->count();

        $accepted = DB::table(self::TABLE_INVITATIONS)
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->whereNotNull('accepted_at')
            ->count();

        $pending = DB::table(self::TABLE_INVITATIONS)
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->count();

        $expired = DB::table(self::TABLE_INVITATIONS)
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->whereNull('accepted_at')
            ->where('expires_at', '<=', now())
            ->count();

        return [
            'total_invitations' => $totalInvitations,
            'accepted' => $accepted,
            'pending' => $pending,
            'expired' => $expired,
            'acceptance_rate' => $totalInvitations > 0
                ? round(($accepted / $totalInvitations) * 100, 2)
                : 0,
        ];
    }

    // =========================================================================
    // MAPPER METHODS
    // =========================================================================

    private function toDomainEntity(stdClass $data): PortalUserEntity
    {
        return PortalUserEntity::reconstitute(
            id: $data->id,
            createdAt: isset($data->created_at) ? new DateTimeImmutable($data->created_at) : null,
            updatedAt: isset($data->updated_at) ? new DateTimeImmutable($data->updated_at) : null,
        );
    }

    private function toModelData(PortalUserEntity $entity): array
    {
        $data = [];

        if ($entity->getCreatedAt()) {
            $data['created_at'] = $entity->getCreatedAt()->format('Y-m-d H:i:s');
        }

        if ($entity->getUpdatedAt()) {
            $data['updated_at'] = $entity->getUpdatedAt()->format('Y-m-d H:i:s');
        }

        return $data;
    }

    private function toArray(stdClass $obj): array
    {
        $array = (array) $obj;
        $result = [];

        foreach ($array as $key => $value) {
            // Handle JSON fields
            if (is_string($value) && $this->isJson($value)) {
                $result[$key] = json_decode($value, true);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function isJson(string $string): bool
    {
        if (empty($string) || !in_array($string[0], ['{', '['])) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
