<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Call;

use App\Domain\Call\Repositories\CallQueueMemberRepositoryInterface;
use Illuminate\Support\Facades\DB;

class DbCallQueueMemberRepository implements CallQueueMemberRepositoryInterface
{
    private const TABLE = 'call_queue_members';

    public function findByQueueAndUser(int $queueId, int $userId): ?array
    {
        $member = DB::table(self::TABLE)
            ->where('queue_id', $queueId)
            ->where('user_id', $userId)
            ->first();

        return $member ? (array) $member : null;
    }

    public function findByQueueId(int $queueId): array
    {
        return DB::table(self::TABLE)
            ->where('queue_id', $queueId)
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();
    }

    public function findByUserId(int $userId): array
    {
        return DB::table(self::TABLE)
            ->join('call_queues', 'call_queue_members.queue_id', '=', 'call_queues.id')
            ->where('call_queue_members.user_id', $userId)
            ->select(
                'call_queue_members.*',
                'call_queues.name as queue_name'
            )
            ->get()
            ->map(fn($row) => [
                'queue_id' => (int) $row->queue_id,
                'queue_name' => $row->queue_name,
                'status' => $row->status,
                'is_active' => (bool) $row->is_active,
                'priority' => (int) $row->priority,
                'calls_handled_today' => (int) $row->calls_handled_today,
                'last_call_at' => $row->last_call_at,
            ])
            ->toArray();
    }

    public function create(int $queueId, array $data): array
    {
        $now = now();
        $insertData = [
            'queue_id' => $queueId,
            'user_id' => $data['user_id'],
            'priority' => $data['priority'] ?? 5,
            'is_active' => $data['is_active'] ?? true,
            'status' => $data['status'] ?? 'offline',
            'calls_handled_today' => $data['calls_handled_today'] ?? 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $id = DB::table(self::TABLE)->insertGetId($insertData);

        $member = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->getMemberWithUser((array) $member);
    }

    public function update(int $queueId, int $userId, array $data): ?array
    {
        $updateData = array_merge($data, ['updated_at' => now()]);

        $updated = DB::table(self::TABLE)
            ->where('queue_id', $queueId)
            ->where('user_id', $userId)
            ->update($updateData);

        if (!$updated) {
            return null;
        }

        $member = $this->findByQueueAndUser($queueId, $userId);

        return $member ? $this->getMemberWithUser($member) : null;
    }

    public function delete(int $queueId, int $userId): bool
    {
        return DB::table(self::TABLE)
            ->where('queue_id', $queueId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    public function deleteByQueueId(int $queueId): int
    {
        return DB::table(self::TABLE)->where('queue_id', $queueId)->delete();
    }

    public function setStatus(int $queueId, int $userId, string $status): bool
    {
        return DB::table(self::TABLE)
            ->where('queue_id', $queueId)
            ->where('user_id', $userId)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]) > 0;
    }

    public function setStatusForUser(int $userId, string $status, ?int $queueId = null): int
    {
        $query = DB::table(self::TABLE)->where('user_id', $userId);

        if ($queueId !== null) {
            $query->where('queue_id', $queueId);
        }

        return $query->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    public function resetDailyStats(int $queueId): int
    {
        return DB::table(self::TABLE)
            ->where('queue_id', $queueId)
            ->update([
                'calls_handled_today' => 0,
                'updated_at' => now(),
            ]);
    }

    public function exists(int $queueId, int $userId): bool
    {
        return DB::table(self::TABLE)
            ->where('queue_id', $queueId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function getMemberWithUser(array $member): array
    {
        $user = DB::table('users')
            ->where('id', $member['user_id'])
            ->select('id', 'name', 'email')
            ->first();

        $member['user'] = $user ? (array) $user : null;

        return $member;
    }
}
