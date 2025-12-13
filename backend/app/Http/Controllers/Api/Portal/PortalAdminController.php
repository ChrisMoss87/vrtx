<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalUser;
use App\Models\PortalInvitation;
use App\Models\PortalAnnouncement;
use App\Services\Portal\PortalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PortalAdminController extends Controller
{
    public function __construct(
        private PortalService $portalService
    ) {}

    // Portal Users Management
    public function users(Request $request): JsonResponse
    {
        $query = PortalUser::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('account_id')) {
            $query->where('account_id', $request->input('account_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    public function user(int $id): JsonResponse
    {
        $user = PortalUser::with(['activityLogs' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(20);
        }])->findOrFail($id);

        return response()->json(['user' => $user]);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = PortalUser::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|in:admin,member,viewer',
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($validated);

        return response()->json(['user' => $user->fresh()]);
    }

    public function deactivateUser(int $id): JsonResponse
    {
        $user = PortalUser::findOrFail($id);
        $user->update(['is_active' => false]);

        // Revoke all tokens
        $user->accessTokens()->delete();

        return response()->json(['message' => 'User deactivated successfully']);
    }

    public function activateUser(int $id): JsonResponse
    {
        $user = PortalUser::findOrFail($id);
        $user->update(['is_active' => true]);

        return response()->json(['message' => 'User activated successfully']);
    }

    // Invitations Management
    public function invitations(Request $request): JsonResponse
    {
        $query = PortalInvitation::with('inviter');

        if ($request->has('status')) {
            switch ($request->input('status')) {
                case 'pending':
                    $query->whereNull('accepted_at')->where('expires_at', '>', now());
                    break;
                case 'accepted':
                    $query->whereNotNull('accepted_at');
                    break;
                case 'expired':
                    $query->whereNull('accepted_at')->where('expires_at', '<=', now());
                    break;
            }
        }

        $invitations = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($invitations);
    }

    public function createInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'contact_id' => 'nullable|integer',
            'account_id' => 'nullable|integer',
            'role' => 'required|string|in:admin,member,viewer',
        ]);

        // Check if user already exists
        if (PortalUser::where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'A portal user with this email already exists',
            ], 422);
        }

        $invitation = $this->portalService->createInvitation(
            $validated['email'],
            $validated['contact_id'] ?? null,
            $validated['account_id'] ?? null,
            $validated['role'],
            auth()->id()
        );

        // TODO: Send invitation email

        return response()->json([
            'invitation' => $invitation->load('inviter'),
            'message' => 'Invitation created successfully',
        ], 201);
    }

    public function resendInvitation(int $id): JsonResponse
    {
        $invitation = PortalInvitation::findOrFail($id);

        if ($invitation->isAccepted()) {
            return response()->json([
                'message' => 'Invitation has already been accepted',
            ], 400);
        }

        // Generate new token and extend expiry
        $invitation->update([
            'token' => PortalInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        // TODO: Resend invitation email

        return response()->json([
            'invitation' => $invitation->fresh(),
            'message' => 'Invitation resent successfully',
        ]);
    }

    public function cancelInvitation(int $id): JsonResponse
    {
        $invitation = PortalInvitation::findOrFail($id);

        if ($invitation->isAccepted()) {
            return response()->json([
                'message' => 'Cannot cancel accepted invitation',
            ], 400);
        }

        $invitation->update(['expires_at' => now()]);

        return response()->json(['message' => 'Invitation cancelled']);
    }

    // Announcements Management
    public function announcements(Request $request): JsonResponse
    {
        $query = PortalAnnouncement::with('creator');

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $announcements = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($announcements);
    }

    public function createAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|string|in:info,warning,success,error',
            'is_active' => 'boolean',
            'is_dismissible' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'target_accounts' => 'array',
            'target_accounts.*' => 'integer',
        ]);

        $announcement = PortalAnnouncement::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'announcement' => $announcement->load('creator'),
        ], 201);
    }

    public function updateAnnouncement(Request $request, int $id): JsonResponse
    {
        $announcement = PortalAnnouncement::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'type' => 'sometimes|string|in:info,warning,success,error',
            'is_active' => 'sometimes|boolean',
            'is_dismissible' => 'sometimes|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'target_accounts' => 'sometimes|array',
        ]);

        $announcement->update($validated);

        return response()->json([
            'announcement' => $announcement->fresh()->load('creator'),
        ]);
    }

    public function deleteAnnouncement(int $id): JsonResponse
    {
        $announcement = PortalAnnouncement::findOrFail($id);
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted']);
    }

    // Document Sharing
    public function shareDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => 'required|string|in:quote,invoice,contract,proposal,file',
            'document_id' => 'required|integer',
            'portal_user_id' => 'nullable|integer|exists:portal_users,id',
            'account_id' => 'nullable|integer',
            'can_download' => 'boolean',
            'requires_signature' => 'boolean',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $share = $this->portalService->shareDocument(
            $validated['document_type'],
            $validated['document_id'],
            $validated['portal_user_id'] ?? null,
            $validated['account_id'] ?? null,
            auth()->id(),
            [
                'can_download' => $validated['can_download'] ?? true,
                'requires_signature' => $validated['requires_signature'] ?? false,
                'expires_at' => $validated['expires_at'] ?? null,
            ]
        );

        return response()->json([
            'share' => $share,
            'message' => 'Document shared successfully',
        ], 201);
    }

    // Activity Analytics
    public function activityAnalytics(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days);

        $loginsByDay = \DB::table('portal_activity_logs')
            ->where('action', 'login')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topActions = \DB::table('portal_activity_logs')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $activeUsers = PortalUser::where('last_login_at', '>=', $startDate)->count();
        $totalUsers = PortalUser::where('is_active', true)->count();

        return response()->json([
            'logins_by_day' => $loginsByDay,
            'top_actions' => $topActions,
            'active_users' => $activeUsers,
            'total_users' => $totalUsers,
            'engagement_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0,
        ]);
    }
}
