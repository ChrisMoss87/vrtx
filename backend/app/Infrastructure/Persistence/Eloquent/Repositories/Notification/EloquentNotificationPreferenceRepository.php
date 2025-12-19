<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Notification;

use App\Domain\Notification\Repositories\NotificationPreferenceRepositoryInterface;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentNotificationPreferenceRepository implements NotificationPreferenceRepositoryInterface
{
    public function getForUser(int $userId): Collection
    {
        return NotificationPreference::where('user_id', $userId)
            ->get()
            ->keyBy('category');
    }

    public function getForCategory(int $userId, string $category): ?array
    {
        $preference = NotificationPreference::where('user_id', $userId)
            ->where('category', $category)
            ->first();

        return $preference?->toArray();
    }

    public function updateOrCreate(int $userId, string $category, array $settings): array
    {
        $preference = NotificationPreference::updateOrCreate(
            ['user_id' => $userId, 'category' => $category],
            $settings
        );

        return $preference->toArray();
    }

    public function updateMany(int $userId, array $preferences): void
    {
        DB::transaction(function () use ($userId, $preferences) {
            foreach ($preferences as $category => $settings) {
                NotificationPreference::updateOrCreate(
                    ['user_id' => $userId, 'category' => $category],
                    $settings
                );
            }
        });
    }

    public function getDefaults(): array
    {
        return NotificationPreference::getDefaults();
    }
}
