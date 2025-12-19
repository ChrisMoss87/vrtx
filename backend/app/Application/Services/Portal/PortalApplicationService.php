<?php

declare(strict_types=1);

namespace App\Application\Services\Portal;

use App\Models\PortalAccessToken;
use App\Models\PortalActivityLog;
use App\Models\PortalAnnouncement;
use App\Models\PortalDocumentShare;
use App\Models\PortalInvitation;
use App\Models\PortalNotification;
use App\Models\PortalUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PortalApplicationService
{
    // =========================================================================
    // QUERY USE CASES - PORTAL USERS
    // =========================================================================

    /**
     * List portal users with filtering and pagination.
     */
    public function listUsers(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = PortalUser::query();

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

        return $query->paginate($perPage);
    }

    /**
     * Get a single portal user by ID.
     */
    public function getUser(int $id): ?PortalUser
    {
        return PortalUser::with([
            'accessTokens',
            'activityLogs' => fn($q) => $q->latest()->limit(20),
            'documentShares',
            'notifications' => fn($q) => $q->latest()->limit(10)
        ])->find($id);
    }

    /**
     * Get a portal user by email.
     */
    public function getUserByEmail(string $email): ?PortalUser
    {
        return PortalUser::where('email', $email)->first();
    }

    /**
     * Get users by account.
     */
    public function getUsersByAccount(int $accountId): Collection
    {
        return PortalUser::where('account_id', $accountId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get active portal users.
     */
    public function getActiveUsers(): Collection
    {
        return PortalUser::where('status', PortalUser::STATUS_ACTIVE)
            ->orderBy('last_login_at', 'desc')
            ->get();
    }

    /**
     * Get pending portal users.
     */
    public function getPendingUsers(): Collection
    {
        return PortalUser::where('status', PortalUser::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // =========================================================================
    // QUERY USE CASES - INVITATIONS
    // =========================================================================

    /**
     * List invitations with filtering and pagination.
     */
    public function listInvitations(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = PortalInvitation::query()->with(['inviter:id,name,email']);

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

        return $query->paginate($perPage);
    }

    /**
     * Get pending invitations for an account.
     */
    public function getPendingInvitations(int $accountId): Collection
    {
        return PortalInvitation::where('account_id', $accountId)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get invitation by token.
     */
    public function getInvitationByToken(string $token): ?PortalInvitation
    {
        return PortalInvitation::where('token', $token)->first();
    }

    // =========================================================================
    // QUERY USE CASES - ACTIVITY LOGS
    // =========================================================================

    /**
     * Get activity logs for a portal user.
     */
    public function getActivityLogs(int $portalUserId, int $limit = 50): Collection
    {
        return PortalActivityLog::where('portal_user_id', $portalUserId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get activity logs for an account.
     */
    public function getAccountActivityLogs(int $accountId, int $limit = 100): Collection
    {
        return PortalActivityLog::whereHas('portalUser', function ($q) use ($accountId) {
            $q->where('account_id', $accountId);
        })
            ->with('portalUser:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent logins.
     */
    public function getRecentLogins(int $days = 30): Collection
    {
        return PortalActivityLog::where('action', 'login')
            ->where('created_at', '>=', now()->subDays($days))
            ->with('portalUser:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // =========================================================================
    // QUERY USE CASES - DOCUMENT SHARES
    // =========================================================================

    /**
     * Get document shares for a portal user.
     */
    public function getDocumentShares(int $portalUserId): Collection
    {
        return PortalDocumentShare::where('portal_user_id', $portalUserId)
            ->with('sharer:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get document shares by type.
     */
    public function getDocumentSharesByType(int $portalUserId, string $documentType): Collection
    {
        return PortalDocumentShare::where('portal_user_id', $portalUserId)
            ->where('document_type', $documentType)
            ->with('sharer:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get documents requiring signature.
     */
    public function getDocumentsRequiringSignature(int $portalUserId): Collection
    {
        return PortalDocumentShare::where('portal_user_id', $portalUserId)
            ->where('requires_signature', true)
            ->whereNull('signed_at')
            ->with('sharer:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // =========================================================================
    // QUERY USE CASES - NOTIFICATIONS
    // =========================================================================

    /**
     * Get notifications for a portal user.
     */
    public function getNotifications(int $portalUserId, int $limit = 50): Collection
    {
        return PortalNotification::where('portal_user_id', $portalUserId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get unread notifications.
     */
    public function getUnreadNotifications(int $portalUserId): Collection
    {
        return PortalNotification::where('portal_user_id', $portalUserId)
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get unread notification count.
     */
    public function getUnreadNotificationCount(int $portalUserId): int
    {
        return PortalNotification::where('portal_user_id', $portalUserId)
            ->whereNull('read_at')
            ->count();
    }

    // =========================================================================
    // QUERY USE CASES - ANNOUNCEMENTS
    // =========================================================================

    /**
     * Get active announcements for a portal user.
     */
    public function getActiveAnnouncements(?int $accountId = null): Collection
    {
        $query = PortalAnnouncement::active();

        if ($accountId) {
            $query->forAccount($accountId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * List all announcements with filtering.
     */
    public function listAnnouncements(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = PortalAnnouncement::query()->with(['creator:id,name,email']);

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

        return $query->paginate($perPage);
    }

    // =========================================================================
    // COMMAND USE CASES - PORTAL USERS
    // =========================================================================

    /**
     * Create a portal user.
     */
    public function createUser(array $data): PortalUser
    {
        return DB::transaction(function () use ($data) {
            $user = PortalUser::create([
                'email' => $data['email'],
                'password' => $data['password'] ?? null,
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'contact_module' => $data['contact_module'] ?? null,
                'account_id' => $data['account_id'] ?? null,
                'status' => $data['status'] ?? PortalUser::STATUS_PENDING,
                'verification_token' => $data['verification_token'] ?? bin2hex(random_bytes(32)),
                'preferences' => $data['preferences'] ?? [],
                'timezone' => $data['timezone'] ?? config('app.timezone'),
                'locale' => $data['locale'] ?? config('app.locale'),
            ]);

            // Log creation
            $user->logActivity('account_created');

            return $user;
        });
    }

    /**
     * Update a portal user.
     */
    public function updateUser(int $id, array $data): PortalUser
    {
        $user = PortalUser::findOrFail($id);

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

        $user->update($updateData);

        return $user->fresh();
    }

    /**
     * Delete a portal user.
     */
    public function deleteUser(int $id): bool
    {
        $user = PortalUser::findOrFail($id);
        return $user->delete();
    }

    /**
     * Activate a portal user.
     */
    public function activateUser(int $id): PortalUser
    {
        return DB::transaction(function () use ($id) {
            $user = PortalUser::findOrFail($id);
            $user->activate();
            $user->logActivity('account_activated');
            return $user->fresh();
        });
    }

    /**
     * Suspend a portal user.
     */
    public function suspendUser(int $id, ?string $reason = null): PortalUser
    {
        return DB::transaction(function () use ($id, $reason) {
            $user = PortalUser::findOrFail($id);
            $user->suspend();
            $user->logActivity('account_suspended', null, null, ['reason' => $reason]);
            return $user->fresh();
        });
    }

    /**
     * Verify email for a portal user.
     */
    public function verifyEmail(string $token): PortalUser
    {
        return DB::transaction(function () use ($token) {
            $user = PortalUser::where('verification_token', $token)->firstOrFail();
            $user->activate();
            return $user;
        });
    }

    /**
     * Change password for a portal user.
     */
    public function changePassword(int $id, string $currentPassword, string $newPassword): PortalUser
    {
        $user = PortalUser::findOrFail($id);

        if (!Hash::check($currentPassword, $user->password)) {
            throw new \InvalidArgumentException('Current password is incorrect');
        }

        $user->update(['password' => $newPassword]);
        $user->logActivity('change_password');

        return $user->fresh();
    }

    /**
     * Record a login for a portal user.
     */
    public function recordLogin(int $id, string $ipAddress): PortalUser
    {
        $user = PortalUser::findOrFail($id);
        $user->recordLogin($ipAddress);
        return $user->fresh();
    }

    // =========================================================================
    // COMMAND USE CASES - INVITATIONS
    // =========================================================================

    /**
     * Create an invitation.
     */
    public function createInvitation(array $data): PortalInvitation
    {
        return PortalInvitation::create([
            'email' => $data['email'],
            'token' => PortalInvitation::generateToken(),
            'contact_id' => $data['contact_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'role' => $data['role'] ?? 'user',
            'invited_by' => Auth::id(),
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
        ]);
    }

    /**
     * Accept an invitation.
     */
    public function acceptInvitation(string $token, array $userData): PortalUser
    {
        return DB::transaction(function () use ($token, $userData) {
            $invitation = PortalInvitation::where('token', $token)->firstOrFail();

            if ($invitation->isExpired()) {
                throw new \InvalidArgumentException('Invitation has expired');
            }

            if ($invitation->isAccepted()) {
                throw new \InvalidArgumentException('Invitation has already been accepted');
            }

            // Create portal user
            $user = $this->createUser([
                'email' => $invitation->email,
                'password' => $userData['password'],
                'name' => $userData['name'],
                'phone' => $userData['phone'] ?? null,
                'contact_id' => $invitation->contact_id,
                'account_id' => $invitation->account_id,
                'status' => PortalUser::STATUS_ACTIVE,
            ]);

            // Mark invitation as accepted
            $invitation->accept();

            return $user;
        });
    }

    /**
     * Resend an invitation.
     */
    public function resendInvitation(int $id): PortalInvitation
    {
        $invitation = PortalInvitation::findOrFail($id);

        if ($invitation->isAccepted()) {
            throw new \InvalidArgumentException('Invitation has already been accepted');
        }

        // Update expiration date
        $invitation->update(['expires_at' => now()->addDays(7)]);

        return $invitation->fresh();
    }

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(int $id): bool
    {
        $invitation = PortalInvitation::findOrFail($id);
        return $invitation->delete();
    }

    // =========================================================================
    // COMMAND USE CASES - ACCESS TOKENS
    // =========================================================================

    /**
     * Create an access token for a portal user.
     */
    public function createAccessToken(int $portalUserId, string $name, array $abilities = ['*'], ?\DateTime $expiresAt = null): PortalAccessToken
    {
        $user = PortalUser::findOrFail($portalUserId);
        return $user->createToken($name, $abilities, $expiresAt);
    }

    /**
     * Revoke an access token.
     */
    public function revokeAccessToken(int $tokenId): bool
    {
        $token = PortalAccessToken::findOrFail($tokenId);
        return $token->delete();
    }

    /**
     * Revoke all access tokens for a user.
     */
    public function revokeAllAccessTokens(int $portalUserId): int
    {
        return PortalAccessToken::where('portal_user_id', $portalUserId)->delete();
    }

    // =========================================================================
    // COMMAND USE CASES - DOCUMENT SHARES
    // =========================================================================

    /**
     * Share a document with a portal user.
     */
    public function shareDocument(int $portalUserId, array $data): PortalDocumentShare
    {
        return DB::transaction(function () use ($portalUserId, $data) {
            $share = PortalDocumentShare::create([
                'portal_user_id' => $portalUserId,
                'account_id' => $data['account_id'] ?? null,
                'document_type' => $data['document_type'],
                'document_id' => $data['document_id'],
                'can_download' => $data['can_download'] ?? true,
                'requires_signature' => $data['requires_signature'] ?? false,
                'expires_at' => $data['expires_at'] ?? null,
                'shared_by' => Auth::id(),
            ]);

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
    public function recordDocumentView(int $shareId): PortalDocumentShare
    {
        $share = PortalDocumentShare::findOrFail($shareId);
        $share->recordView();
        return $share->fresh();
    }

    /**
     * Sign a document.
     */
    public function signDocument(int $shareId, string $ipAddress): PortalDocumentShare
    {
        return DB::transaction(function () use ($shareId, $ipAddress) {
            $share = PortalDocumentShare::findOrFail($shareId);

            if (!$share->requires_signature) {
                throw new \InvalidArgumentException('Document does not require signature');
            }

            if ($share->isSigned()) {
                throw new \InvalidArgumentException('Document has already been signed');
            }

            $share->sign($ipAddress);

            // Notify the sharer
            // Could send email notification here

            return $share->fresh();
        });
    }

    /**
     * Revoke document share.
     */
    public function revokeDocumentShare(int $shareId): bool
    {
        $share = PortalDocumentShare::findOrFail($shareId);
        return $share->delete();
    }

    // =========================================================================
    // COMMAND USE CASES - NOTIFICATIONS
    // =========================================================================

    /**
     * Create a notification for a portal user.
     */
    public function createNotification(int $portalUserId, array $data): PortalNotification
    {
        return PortalNotification::create([
            'portal_user_id' => $portalUserId,
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'action_url' => $data['action_url'] ?? null,
            'data' => $data['data'] ?? [],
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markNotificationAsRead(int $notificationId): PortalNotification
    {
        $notification = PortalNotification::findOrFail($notificationId);
        $notification->markAsRead();
        return $notification->fresh();
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllNotificationsAsRead(int $portalUserId): int
    {
        return PortalNotification::where('portal_user_id', $portalUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Delete a notification.
     */
    public function deleteNotification(int $notificationId): bool
    {
        $notification = PortalNotification::findOrFail($notificationId);
        return $notification->delete();
    }

    // =========================================================================
    // COMMAND USE CASES - ANNOUNCEMENTS
    // =========================================================================

    /**
     * Create an announcement.
     */
    public function createAnnouncement(array $data): PortalAnnouncement
    {
        return PortalAnnouncement::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type'] ?? 'info',
            'is_active' => $data['is_active'] ?? true,
            'is_dismissible' => $data['is_dismissible'] ?? true,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'target_accounts' => $data['target_accounts'] ?? [],
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Update an announcement.
     */
    public function updateAnnouncement(int $id, array $data): PortalAnnouncement
    {
        $announcement = PortalAnnouncement::findOrFail($id);

        $announcement->update(array_filter([
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'type' => $data['type'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'is_dismissible' => $data['is_dismissible'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'target_accounts' => $data['target_accounts'] ?? null,
        ], fn($value) => $value !== null));

        return $announcement->fresh();
    }

    /**
     * Delete an announcement.
     */
    public function deleteAnnouncement(int $id): bool
    {
        $announcement = PortalAnnouncement::findOrFail($id);
        return $announcement->delete();
    }

    // =========================================================================
    // ANALYTICS & REPORTING
    // =========================================================================

    /**
     * Get portal user statistics.
     */
    public function getUserStatistics(): array
    {
        $totalUsers = PortalUser::count();
        $activeUsers = PortalUser::where('status', PortalUser::STATUS_ACTIVE)->count();
        $pendingUsers = PortalUser::where('status', PortalUser::STATUS_PENDING)->count();
        $suspendedUsers = PortalUser::where('status', PortalUser::STATUS_SUSPENDED)->count();

        $recentLogins = PortalUser::whereNotNull('last_login_at')
            ->where('last_login_at', '>=', now()->subDays(30))
            ->count();

        $emailVerified = PortalUser::whereNotNull('email_verified_at')->count();

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

    /**
     * Get document share statistics.
     */
    public function getDocumentShareStatistics(?int $accountId = null): array
    {
        $query = PortalDocumentShare::query();

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $totalShares = $query->count();
        $requiresSignature = $query->where('requires_signature', true)->count();
        $signed = $query->whereNotNull('signed_at')->count();
        $pendingSignature = $query->where('requires_signature', true)
            ->whereNull('signed_at')
            ->count();

        $sharesByType = PortalDocumentShare::when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->selectRaw('document_type, COUNT(*) as count')
            ->groupBy('document_type')
            ->pluck('count', 'document_type')
            ->toArray();

        return [
            'total_shares' => $totalShares,
            'requires_signature' => $requiresSignature,
            'signed' => $signed,
            'pending_signature' => $pendingSignature,
            'shares_by_type' => $sharesByType,
        ];
    }

    /**
     * Get activity summary for a portal user.
     */
    public function getUserActivitySummary(int $portalUserId, int $days = 30): array
    {
        $user = PortalUser::findOrFail($portalUserId);

        $activityCount = PortalActivityLog::where('portal_user_id', $portalUserId)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();

        $activityByType = PortalActivityLog::where('portal_user_id', $portalUserId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        $documentsViewed = PortalDocumentShare::where('portal_user_id', $portalUserId)
            ->whereNotNull('first_viewed_at')
            ->count();

        $documentsSigned = PortalDocumentShare::where('portal_user_id', $portalUserId)
            ->whereNotNull('signed_at')
            ->count();

        return [
            'user_id' => $portalUserId,
            'period_days' => $days,
            'total_activities' => $activityCount,
            'activities_by_type' => $activityByType,
            'documents_viewed' => $documentsViewed,
            'documents_signed' => $documentsSigned,
            'last_login' => $user->last_login_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get invitation statistics.
     */
    public function getInvitationStatistics(?int $accountId = null): array
    {
        $query = PortalInvitation::query();

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $totalInvitations = $query->count();
        $accepted = $query->whereNotNull('accepted_at')->count();
        $pending = $query->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->count();
        $expired = $query->whereNull('accepted_at')
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
}
