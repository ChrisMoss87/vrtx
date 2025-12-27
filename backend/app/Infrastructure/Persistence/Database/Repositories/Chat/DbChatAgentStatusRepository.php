<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Chat;

use App\Domain\Chat\Repositories\ChatAgentStatusRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DbChatAgentStatusRepository implements ChatAgentStatusRepositoryInterface
{
    private const TABLE = 'chat_agent_statuses';
    private const TABLE_USERS = 'users';

    private const STATUS_ONLINE = 'online';
    private const STATUS_AWAY = 'away';
    private const STATUS_BUSY = 'busy';
    private const STATUS_OFFLINE = 'offline';

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->enrichWithUser((array) $row);
    }

    public function findByUserId(int $userId): ?array
    {
        $row = DB::table(self::TABLE)->where('user_id', $userId)->first();

        if (!$row) {
            return null;
        }

        return $this->enrichWithUser((array) $row);
    }

    public function getOrCreate(int $userId): array
    {
        $existing = DB::table(self::TABLE)->where('user_id', $userId)->first();

        if ($existing) {
            return $this->enrichWithUser((array) $existing);
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'user_id' => $userId,
            'status' => self::STATUS_OFFLINE,
            'max_conversations' => 5,
            'active_conversations' => 0,
            'departments' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findById($id);
    }

    public function setStatus(int $userId, string $status): array
    {
        $this->getOrCreate($userId);

        $updateData = [
            'status' => $status,
            'updated_at' => now(),
        ];

        if ($status === self::STATUS_ONLINE) {
            $updateData['last_activity_at'] = now();
        }

        DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->update($updateData);

        return $this->findByUserId($userId);
    }

    public function recordActivity(int $userId): array
    {
        DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->update([
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->findByUserId($userId);
    }

    public function updateSettings(int $userId, array $data): array
    {
        $updateData = ['updated_at' => now()];

        if (isset($data['max_conversations'])) {
            $updateData['max_conversations'] = $data['max_conversations'];
        }

        if (isset($data['departments'])) {
            $updateData['departments'] = json_encode($data['departments']);
        }

        DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->update($updateData);

        return $this->findByUserId($userId);
    }

    public function findOnline(): Collection
    {
        return DB::table(self::TABLE . ' as s')
            ->join(self::TABLE_USERS . ' as u', 'u.id', '=', 's.user_id')
            ->where('s.status', self::STATUS_ONLINE)
            ->select('s.*', 'u.name as user_name', 'u.email as user_email')
            ->get()
            ->map(fn($row) => $this->formatRow($row));
    }

    public function findAvailable(?string $department = null): Collection
    {
        $query = DB::table(self::TABLE . ' as s')
            ->join(self::TABLE_USERS . ' as u', 'u.id', '=', 's.user_id')
            ->where('s.status', self::STATUS_ONLINE)
            ->whereRaw('s.active_conversations < s.max_conversations')
            ->select('s.*', 'u.name as user_name', 'u.email as user_email');

        if ($department) {
            $query->whereRaw("s.departments::jsonb ? ?", [$department]);
        }

        return $query
            ->orderBy('s.active_conversations')
            ->get()
            ->map(fn($row) => $this->formatRow($row));
    }

    public function findBestAvailableAgent(?array $routingRules, ?string $department): ?array
    {
        $query = DB::table(self::TABLE)
            ->where('status', self::STATUS_ONLINE)
            ->whereRaw('active_conversations < max_conversations');

        if ($department) {
            $query->whereRaw("departments::jsonb ? ?", [$department]);
        }

        $agent = $query
            ->orderBy('active_conversations')
            ->orderByDesc('last_activity_at')
            ->first();

        if (!$agent) {
            return null;
        }

        return $this->enrichWithUser((array) $agent);
    }

    private function enrichWithUser(array $data): array
    {
        $user = DB::table(self::TABLE_USERS)
            ->where('id', $data['user_id'])
            ->select('id', 'name', 'email')
            ->first();

        $data['user'] = $user ? (array) $user : null;

        return $data;
    }

    private function formatRow(object $row): array
    {
        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'status' => $row->status,
            'max_conversations' => $row->max_conversations,
            'active_conversations' => $row->active_conversations,
            'departments' => $row->departments ? json_decode($row->departments, true) : [],
            'last_activity_at' => $row->last_activity_at,
            'user' => [
                'id' => $row->user_id,
                'name' => $row->user_name,
                'email' => $row->user_email,
            ],
        ];
    }
}
