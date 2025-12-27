<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\Authorization\AuthorizationApplicationService;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Infrastructure\Authorization\CachedAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly AuthorizationApplicationService $authService,
        private readonly CachedAuthorizationService $cachedAuthService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->userRepository->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        // Assign default role to new users using our service
        $defaultRole = $this->roleRepository->findByName('sales_rep');
        if ($defaultRole) {
            $this->authService->assignRoleToUser($user['id'], $defaultRole['id']);
        }

        // Create token using the Eloquent model (Sanctum requirement)
        $eloquentUser = Auth::getProvider()->retrieveById($user['id']);
        $token = $eloquentUser->createToken('auth-token')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'roles' => $this->getUserRoleNames($user['id']),
                    'permissions' => $this->cachedAuthService->getUserPermissions($user['id']),
                ],
                'token' => $token,
            ],
            'message' => 'Registration successful',
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $this->getUserRoleNames($user->id),
                    'permissions' => $this->cachedAuthService->getUserPermissions($user->id),
                ],
                'token' => $token,
            ],
            'message' => 'Login successful',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $this->getUserRoleNames($user->id),
                'permissions' => $this->cachedAuthService->getUserPermissions($user->id),
            ],
        ]);
    }

    /**
     * Get role names for a user using repository.
     */
    private function getUserRoleNames(int $userId): array
    {
        $roles = $this->userRepository->getUserRoles($userId);

        return array_column($roles, 'name');
    }
}
