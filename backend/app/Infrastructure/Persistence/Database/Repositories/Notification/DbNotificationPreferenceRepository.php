<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Notification;

use App\Domain\Notification\Repositories\NotificationPreferenceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbNotificationPreferenceRepository implements NotificationPreferenceRepositoryInterface
{
    private const TABLE = 'notification_preferences';

    // Default notification preference categories
    private const DEFAULT_CATEGORIES = [
        'deals' => ['email' => true, 'in_app' => true, 'push' => false],
        'tasks' => ['email' => true, 'in_app' => true, 'push' => true],
        'mentions' => ['email' => true, 'in_app' => true, 'push' => true],
        'updates' => ['email' => false, 'in_app' => true, 'push' => false],
        'system' => ['email' => true, 'in_app' => true, 'push' => false],
    ];

    public function getForUser(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->category] = $this->rowToArray($row);
        }

        return $result;
    }

    public function getForCategory(int $userId, string $category): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('category', $category)
            ->first();

        return $row ? $this->rowToArray($row) : null;
    }

    public function updateOrCreate(int $userId, string $category, array $settings): array
    {
        $exists = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('category', $category)
            ->exists();

        if ($exists) {
            DB::table(self::TABLE)
                ->where('user_id', $userId)
                ->where('category', $category)
                ->update(array_merge($settings, ['updated_at' => now()]));
        } else {
            DB::table(self::TABLE)->insert(array_merge($settings, [
                'user_id' => $userId,
                'category' => $category,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $row = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('category', $category)
            ->first();

        return $this->rowToArray($row);
    }

    public function updateMany(int $userId, array $preferences): void
    {
        DB::transaction(function () use ($userId, $preferences) {
            foreach ($preferences as $category => $settings) {
                $this->updateOrCreate($userId, $category, $settings);
            }
        });
    }

    public function getDefaults(): array
    {
        return self::DEFAULT_CATEGORIES;
    }

    private function rowToArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'category' => $row->category,
            'email' => (bool) ($row->email ?? true),
            'in_app' => (bool) ($row->in_app ?? true),
            'push' => (bool) ($row->push ?? false),
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
