<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\User;

use App\Domain\User\Repositories\SessionRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentSessionRepository implements SessionRepositoryInterface
{
    public function getForUser(int $userId): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => date('Y-m-d H:i:s', $session->last_activity),
            ]);
    }

    public function revoke(int $userId, string $sessionId): bool
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    public function revokeAll(int $userId): int
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->delete();
    }

    public function revokeAllTokens(int $userId): int
    {
        $user = User::find($userId);

        if (!$user) {
            return 0;
        }

        return $user->tokens()->delete();
    }
}
