<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Call;

use App\Domain\Call\Repositories\CallQueueRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DbCallQueueRepository implements CallQueueRepositoryInterface
{
    private const TABLE = 'call_queues';
    private const MEMBERS_TABLE = 'call_queue_members';
    private const PROVIDERS_TABLE = 'call_providers';

    public function findAllWithRelations(): array
    {
        $queues = DB::table(self::TABLE)
            ->orderBy('name')
            ->get();

        return $queues->map(function ($queue) {
            $queueArray = (array) $queue;
            $queueArray['provider'] = $this->getProvider((int) $queue->provider_id);
            $queueArray['members'] = $this->getMembersWithUser((int) $queue->id);
            $queueArray['online_agent_count'] = $this->getOnlineAgentCount((int) $queue->id);
            $queueArray['is_within_business_hours'] = $this->isWithinBusinessHours((int) $queue->id);

            return $queueArray;
        })->toArray();
    }

    public function findById(int $id): ?array
    {
        $queue = DB::table(self::TABLE)->where('id', $id)->first();

        return $queue ? (array) $queue : null;
    }

    public function findByIdWithRelations(int $id): ?array
    {
        $queue = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$queue) {
            return null;
        }

        $queueArray = (array) $queue;
        $queueArray['provider'] = $this->getProvider((int) $queue->provider_id);
        $queueArray['members'] = $this->getMembersWithUser((int) $queue->id);
        $queueArray['online_agent_count'] = $this->getOnlineAgentCount((int) $queue->id);
        $queueArray['is_within_business_hours'] = $this->isWithinBusinessHours((int) $queue->id);

        return $queueArray;
    }

    public function create(array $data): array
    {
        $now = now();
        $insertData = array_merge($data, [
            'is_active' => $data['is_active'] ?? true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (isset($insertData['business_hours']) && is_array($insertData['business_hours'])) {
            $insertData['business_hours'] = json_encode($insertData['business_hours']);
        }
        if (isset($insertData['notification_channels']) && is_array($insertData['notification_channels'])) {
            $insertData['notification_channels'] = json_encode($insertData['notification_channels']);
        }

        $id = DB::table(self::TABLE)->insertGetId($insertData);

        return $this->findById($id);
    }

    public function update(int $id, array $data): ?array
    {
        $updateData = array_merge($data, ['updated_at' => now()]);

        if (isset($updateData['business_hours']) && is_array($updateData['business_hours'])) {
            $updateData['business_hours'] = json_encode($updateData['business_hours']);
        }
        if (isset($updateData['notification_channels']) && is_array($updateData['notification_channels'])) {
            $updateData['notification_channels'] = json_encode($updateData['notification_channels']);
        }

        DB::table(self::TABLE)->where('id', $id)->update($updateData);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        // Delete members first
        DB::table(self::MEMBERS_TABLE)->where('queue_id', $id)->delete();

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function toggleActive(int $id): ?array
    {
        $queue = $this->findById($id);
        if (!$queue) {
            return null;
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'is_active' => !$queue['is_active'],
            'updated_at' => now(),
        ]);

        return $this->findById($id);
    }

    public function exists(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->exists();
    }

    public function getOnlineAgentCount(int $queueId): int
    {
        return DB::table(self::MEMBERS_TABLE)
            ->where('queue_id', $queueId)
            ->where('is_active', true)
            ->where('status', 'online')
            ->count();
    }

    public function isWithinBusinessHours(int $queueId): bool
    {
        $queue = $this->findById($queueId);
        if (!$queue || empty($queue['business_hours'])) {
            return true; // No business hours set = always available
        }

        $businessHours = is_string($queue['business_hours'])
            ? json_decode($queue['business_hours'], true)
            : $queue['business_hours'];

        if (empty($businessHours)) {
            return true;
        }

        $now = Carbon::now();
        $dayOfWeek = strtolower($now->format('l'));
        $currentTime = $now->format('H:i');

        if (!isset($businessHours[$dayOfWeek])) {
            return false;
        }

        $dayHours = $businessHours[$dayOfWeek];
        if (!$dayHours['enabled']) {
            return false;
        }

        return $currentTime >= $dayHours['start'] && $currentTime <= $dayHours['end'];
    }

    private function getProvider(int $providerId): ?array
    {
        $provider = DB::table(self::PROVIDERS_TABLE)->where('id', $providerId)->first();

        return $provider ? (array) $provider : null;
    }

    private function getMembersWithUser(int $queueId): array
    {
        return DB::table(self::MEMBERS_TABLE)
            ->join('users', 'call_queue_members.user_id', '=', 'users.id')
            ->where('call_queue_members.queue_id', $queueId)
            ->select(
                'call_queue_members.*',
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->get()
            ->map(function ($member) {
                $memberArray = (array) $member;
                $memberArray['user'] = [
                    'id' => $member->user_id,
                    'name' => $member->user_name,
                    'email' => $member->user_email,
                ];
                unset($memberArray['user_name'], $memberArray['user_email']);

                return $memberArray;
            })
            ->toArray();
    }
}
