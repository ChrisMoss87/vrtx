<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\User\Services\UserService;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private UserService $userService,
    ) {}

    /**
     * List all users with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'role' => 'nullable|string|exists:roles,name',
            'status' => 'nullable|in:active,inactive',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $users = $this->userService->listUsers(
            search: $validated['search'] ?? null,
            role: $validated['role'] ?? null,
            status: $validated['status'] ?? null,
            perPage: $validated['per_page'] ?? 25,
        );

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get a single user.
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getUser($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json(['data' => $user]);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['nullable', 'string', Password::defaults()],
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
            'send_invite' => 'nullable|boolean',
        ]);

        $user = $this->userService->createUser(
            data: [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'] ?? null,
            ],
            roleIds: $validated['roles'] ?? null,
            sendInvite: $validated['send_invite'] ?? false,
        );

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user,
        ], 201);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->userService->userExists($id)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'roles' => 'sometimes|array',
            'roles.*' => 'integer|exists:roles,id',
        ]);

        $user = $this->userService->updateUser(
            id: $id,
            data: [
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
            ],
            roleIds: $validated['roles'] ?? null,
        );

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user,
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->id === $id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        if (!$this->userService->deleteUser($id)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Toggle user active status.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $result = $this->userService->toggleUserStatus($id);

            return response()->json([
                'message' => $result['is_active'] ? 'User activated successfully' : 'User deactivated successfully',
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reset user password.
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        if (!$this->userService->userExists($id)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'new_password' => ['nullable', 'string', Password::defaults()],
            'send_email' => 'nullable|boolean',
        ]);

        $result = $this->userService->resetPassword(
            id: $id,
            newPassword: $validated['new_password'] ?? null,
            sendEmail: $validated['send_email'] ?? true,
        );

        return response()->json($result);
    }

    /**
     * Get user sessions.
     */
    public function sessions(int $id): JsonResponse
    {
        if (!$this->userService->userExists($id)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $sessions = $this->userService->getUserSessions($id);

        return response()->json(['data' => $sessions]);
    }

    /**
     * Revoke a user's session.
     */
    public function revokeSession(int $id, string $sessionId): JsonResponse
    {
        if (!$this->userService->userExists($id)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $this->userService->revokeSession($id, $sessionId);

        return response()->json(['message' => 'Session revoked successfully']);
    }

    /**
     * Revoke all user sessions.
     */
    public function revokeAllSessions(int $id): JsonResponse
    {
        if (!$this->userService->userExists($id)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $this->userService->revokeAllSessions($id);

        return response()->json(['message' => 'All sessions revoked successfully']);
    }
}
