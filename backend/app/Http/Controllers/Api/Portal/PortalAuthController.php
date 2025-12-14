<?php

namespace App\Http\Controllers\Api\Portal;

use App\Application\Services\Portal\PortalApplicationService;
use App\Http\Controllers\Controller;
use App\Models\PortalUser;
use App\Models\PortalInvitation;
use App\Services\Portal\PortalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PortalAuthController extends Controller
{
    public function __construct(
        private PortalService $portalService,
        private PortalApplicationService $appService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = $this->portalService->authenticate(
            $validated['email'],
            $validated['password']
        );

        if (!$user) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!$user->email_verified_at) {
            return response()->json([
                'message' => 'Email not verified',
            ], 403);
        }

        $tokenData = $this->portalService->createToken($user);

        $this->portalService->logActivity(
            $user,
            'login',
            null,
            null,
            [],
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $tokenData['token'],
            'expires_at' => $tokenData['expires_at'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');
        $token = $request->attributes->get('portal_token');

        if ($user && $token) {
            $this->portalService->logActivity(
                $user,
                'logout',
                null,
                null,
                [],
                $request->ip(),
                $request->userAgent()
            );

            $token->delete();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $invitation = PortalInvitation::where('token', $validated['token'])->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invalid invitation token',
            ], 404);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'Invitation has expired',
            ], 410);
        }

        if ($invitation->isAccepted()) {
            return response()->json([
                'message' => 'Invitation has already been used',
            ], 410);
        }

        try {
            $user = $this->portalService->acceptInvitation($invitation, [
                'name' => $validated['name'],
                'password' => $validated['password'],
            ]);

            $tokenData = $this->portalService->createToken($user);

            return response()->json([
                'user' => $this->formatUser($user),
                'token' => $tokenData['token'],
                'expires_at' => $tokenData['expires_at'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function verifyInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $invitation = PortalInvitation::where('token', $validated['token'])->first();

        if (!$invitation) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid invitation token',
            ]);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'valid' => false,
                'message' => 'Invitation has expired',
            ]);
        }

        if ($invitation->isAccepted()) {
            return response()->json([
                'valid' => false,
                'message' => 'Invitation has already been used',
            ]);
        }

        return response()->json([
            'valid' => true,
            'email' => $invitation->email,
            'role' => $invitation->role,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'preferences' => 'sometimes|array',
        ]);

        $user = $this->portalService->updateProfile($user, $validated);

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->attributes->get('portal_user');

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $success = $this->portalService->changePassword(
            $user,
            $validated['current_password'],
            $validated['password']
        );

        if (!$success) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 400);
        }

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    private function formatUser(PortalUser $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'role' => $user->role,
            'contact_id' => $user->contact_id,
            'account_id' => $user->account_id,
            'preferences' => $user->preferences,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
        ];
    }
}
