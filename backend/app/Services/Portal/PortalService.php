<?php

namespace App\Services\Portal;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PortalService
{
    public function createInvitation(
        string $email,
        ?int $contactId,
        ?int $accountId,
        string $role,
        int $invitedBy
    ): PortalInvitation {
        // Cancel any existing pending invitations
        DB::table('portal_invitations')->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        return DB::table('portal_invitations')->insertGetId([
            'email' => $email,
            'token' => PortalInvitation::generateToken(),
            'contact_id' => $contactId,
            'account_id' => $accountId,
            'role' => $role,
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function acceptInvitation(PortalInvitation $invitation, array $data): PortalUser
    {
        if ($invitation->isExpired()) {
            throw new \Exception('Invitation has expired');
        }

        if ($invitation->isAccepted()) {
            throw new \Exception('Invitation has already been accepted');
        }

        $user = DB::table('portal_users')->insertGetId([
            'email' => $invitation->email,
            'password' => Hash::make($data['password']),
            'name' => $data['name'],
            'contact_id' => $invitation->contact_id,
            'account_id' => $invitation->account_id,
            'role' => $invitation->role,
            'email_verified_at' => now(),
        ]);

        $invitation->accept();

        $this->logActivity($user, 'login', null, null, [
            'method' => 'invitation',
        ]);

        return $user;
    }

    public function authenticate(string $email, string $password): ?PortalUser
    {
        $user = DB::table('portal_users')->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $user->update(['last_login_at' => now()]);

        return $user;
    }

    public function createToken(PortalUser $user, string $name = 'api'): array
    {
        $token = $user->createToken($name, ['*'], now()->addDays(30));

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $token->expires_at,
        ];
    }

    public function revokeToken(PortalUser $user, int $tokenId): bool
    {
        return $user->accessTokens()->where('id', $tokenId)->delete() > 0;
    }

    public function revokeAllTokens(PortalUser $user): int
    {
        return $user->accessTokens()->delete();
    }

    public function logActivity(
        PortalUser $user,
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): PortalActivityLog {
        return DB::table('portal_activity_logs')->insertGetId([
            'portal_user_id' => $user->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => $metadata,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function getDealsForUser(PortalUser $user): \Illuminate\Database\Eloquent\Collection
    {
        $query = ModuleRecord::whereHas('module', function ($q) {
            $q->where('api_name', 'deals');
        });

        if ($user->account_id) {
            $query->whereJsonContains('data->account_id', $user->account_id);
        } elseif ($user->contact_id) {
            $query->whereJsonContains('data->contact_id', $user->contact_id);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getInvoicesForUser(PortalUser $user): \Illuminate\Database\Eloquent\Collection
    {
        $query = DB::table('invoices');

        if ($user->account_id) {
            $query->where('account_id', $user->account_id);
        } elseif ($user->contact_id) {
            $query->where('contact_id', $user->contact_id);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getQuotesForUser(PortalUser $user): \Illuminate\Database\Eloquent\Collection
    {
        $query = DB::table('quotes');

        if ($user->account_id) {
            $query->where('account_id', $user->account_id);
        } elseif ($user->contact_id) {
            $query->where('contact_id', $user->contact_id);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getDocumentsForUser(PortalUser $user): \Illuminate\Database\Eloquent\Collection
    {
        return DB::table('portal_document_shares')->where('portal_user_id', $user->id)
            ->orWhere('account_id', $user->account_id)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function shareDocument(
        int $documentType,
        int $documentId,
        ?int $portalUserId,
        ?int $accountId,
        int $sharedBy,
        array $options = []
    ): PortalDocumentShare {
        return DB::table('portal_document_shares')->insertGetId([
            'portal_user_id' => $portalUserId,
            'account_id' => $accountId,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'can_download' => $options['can_download'] ?? true,
            'requires_signature' => $options['requires_signature'] ?? false,
            'expires_at' => $options['expires_at'] ?? null,
            'shared_by' => $sharedBy,
        ]);
    }

    public function sendNotification(
        PortalUser $user,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $data = []
    ): PortalNotification {
        return DB::table('portal_notifications')->insertGetId([
            'portal_user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);
    }

    public function getUnreadNotifications(PortalUser $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->notifications()
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function markNotificationRead(PortalNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllNotificationsRead(PortalUser $user): int
    {
        return $user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function getActiveAnnouncements(?int $accountId = null): \Illuminate\Database\Eloquent\Collection
    {
        return PortalAnnouncement::active()
            ->forAccount($accountId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function updateProfile(PortalUser $user, array $data): PortalUser
    {
        $fillable = ['name', 'phone', 'avatar_url', 'preferences'];
        $user->update(array_intersect_key($data, array_flip($fillable)));

        $this->logActivity($user, 'update_profile');

        return $user->fresh();
    }

    public function changePassword(PortalUser $user, string $currentPassword, string $newPassword): bool
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return false;
        }

        $user->update(['password' => Hash::make($newPassword)]);
        $this->logActivity($user, 'change_password');

        return true;
    }

    public function getDashboardStats(PortalUser $user): array
    {
        $deals = $this->getDealsForUser($user);
        $invoices = $this->getInvoicesForUser($user);
        $quotes = $this->getQuotesForUser($user);
        $documents = $this->getDocumentsForUser($user);

        $openDeals = $deals->filter(fn($d) => !in_array($d->data['stage'] ?? '', ['won', 'lost']));
        $pendingInvoices = $invoices->where('status', 'pending');
        $overdueInvoices = $invoices->where('status', 'overdue');
        $pendingQuotes = $quotes->where('status', 'sent');

        return [
            'deals' => [
                'total' => $deals->count(),
                'open' => $openDeals->count(),
                'total_value' => $openDeals->sum(fn($d) => $d->data['amount'] ?? 0),
            ],
            'invoices' => [
                'total' => $invoices->count(),
                'pending' => $pendingInvoices->count(),
                'overdue' => $overdueInvoices->count(),
                'pending_amount' => $pendingInvoices->sum('total'),
                'overdue_amount' => $overdueInvoices->sum('total'),
            ],
            'quotes' => [
                'total' => $quotes->count(),
                'pending' => $pendingQuotes->count(),
            ],
            'documents' => [
                'total' => $documents->count(),
                'requiring_signature' => $documents->filter(fn($d) => $d->needsSignature())->count(),
            ],
        ];
    }

    public function getActivityLog(PortalUser $user, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return $user->activityLogs()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
