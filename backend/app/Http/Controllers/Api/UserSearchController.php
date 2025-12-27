<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Search users for mentions.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $query = $validated['q'] ?? '';
        $limit = $validated['limit'] ?? 10;

        $users = $this->userRepository->search($query, $limit);

        return response()->json([
            'success' => true,
            'users' => array_map(fn(array $user) => [
                'id' => (string) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'avatar' => null,
            ], $users),
        ]);
    }
}
