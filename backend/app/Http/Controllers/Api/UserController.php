<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use AuthorizesRequests;

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
            'page' => 'nullable|integer|min:1',
        ]);

        $query = User::with('roles');

        // Search filter
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Role filter
        if (!empty($validated['role'])) {
            $query->role($validated['role']);
        }

        // Status filter (using is_active field if exists, otherwise skip)
        if (!empty($validated['status']) && $this->hasIsActiveColumn()) {
            $query->where('is_active', $validated['status'] === 'active');
        }

        $perPage = $validated['per_page'] ?? 25;
        $users = $query->orderBy('name')->paginate($perPage);

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
        $user = User::with('roles')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'is_active' => $user->is_active ?? true,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'roles' => $user->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]),
            ],
        ]);
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

        // Generate a random password if not provided
        $password = $validated['password'] ?? Str::random(16);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($password),
        ]);

        // Assign roles if provided
        if (!empty($validated['roles'])) {
            $roleNames = Role::whereIn('id', $validated['roles'])->pluck('name')->toArray();
            $user->syncRoles($roleNames);
        }

        // TODO: If send_invite is true, send invitation email with password reset link
        // if ($validated['send_invite'] ?? false) {
        //     $user->sendPasswordResetNotification($token);
        // }

        return response()->json([
            'message' => 'User created successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->fresh()->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]),
            ],
        ], 201);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

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

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
            // Reset email verification when email changes
            $user->email_verified_at = null;
        }

        $user->save();

        // Update roles if provided
        if (isset($validated['roles'])) {
            $roleNames = Role::whereIn('id', $validated['roles'])->pluck('name')->toArray();
            $user->syncRoles($roleNames);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->fresh()->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]),
            ],
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Toggle user active status.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (!$this->hasIsActiveColumn()) {
            return response()->json([
                'message' => 'User status toggle is not available',
            ], 422);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'message' => $user->is_active ? 'User activated successfully' : 'User deactivated successfully',
            'data' => [
                'id' => $user->id,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    /**
     * Reset user password (generates a new password or sends reset email).
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'new_password' => ['nullable', 'string', Password::defaults()],
            'send_email' => 'nullable|boolean',
        ]);

        if (!empty($validated['new_password'])) {
            // Set the new password directly
            $user->password = Hash::make($validated['new_password']);
            $user->save();

            return response()->json([
                'message' => 'Password updated successfully',
            ]);
        }

        // Generate a random temporary password
        $tempPassword = Str::random(12);
        $user->password = Hash::make($tempPassword);
        $user->save();

        // TODO: Send password reset email if requested
        // if ($validated['send_email'] ?? true) {
        //     $user->notify(new PasswordResetNotification($tempPassword));
        // }

        return response()->json([
            'message' => 'Password reset successfully',
            'data' => [
                'temporary_password' => $tempPassword,
            ],
        ]);
    }

    /**
     * Get user activity/sessions.
     */
    public function sessions(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Get active sessions from the sessions table
        $sessions = \DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => date('Y-m-d H:i:s', $session->last_activity),
            ]);

        return response()->json([
            'data' => $sessions,
        ]);
    }

    /**
     * Revoke a user's session.
     */
    public function revokeSession(int $id, string $sessionId): JsonResponse
    {
        $user = User::findOrFail($id);

        \DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete();

        return response()->json([
            'message' => 'Session revoked successfully',
        ]);
    }

    /**
     * Revoke all user sessions.
     */
    public function revokeAllSessions(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        \DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        // Also revoke all API tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'All sessions revoked successfully',
        ]);
    }

    /**
     * Check if the users table has is_active column.
     */
    private function hasIsActiveColumn(): bool
    {
        return \Schema::hasColumn('users', 'is_active');
    }
}
