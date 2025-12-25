<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\User;

use App\Domain\User\Repositories\SessionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DbSessionRepository implements SessionRepositoryInterface
{
    private const TABLE = 'sessions';
    private const TABLE_TOKENS = 'personal_access_tokens';

    public function getForUser(int $userId): Collection
    {
        return DB::table(self::TABLE)
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
        return DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    public function revokeAll(int $userId): int
    {
        return DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->delete();
    }

    public function revokeAllTokens(int $userId): int
    {
        return DB::table(self::TABLE_TOKENS)
            ->where('tokenable_type', 'App\\Models\\User')
            ->where('tokenable_id', $userId)
            ->delete();
    }
}
