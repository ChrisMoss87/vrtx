<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Notification;

use App\Domain\Notification\Repositories\NotificationScheduleRepositoryInterface;
use App\Models\NotificationSchedule;

class EloquentNotificationScheduleRepository implements NotificationScheduleRepositoryInterface
{
    public function getOrCreateForUser(int $userId): array
    {
        $schedule = NotificationSchedule::firstOrCreate(
            ['user_id' => $userId],
            ['timezone' => 'UTC']
        );

        return $schedule->toArray();
    }

    public function update(int $userId, array $settings): array
    {
        $schedule = NotificationSchedule::updateOrCreate(
            ['user_id' => $userId],
            $settings
        );

        return $schedule->toArray();
    }

    public function shouldSuppressNotifications(int $userId): bool
    {
        $schedule = NotificationSchedule::where('user_id', $userId)->first();

        if (!$schedule) {
            return false;
        }

        return $schedule->shouldSuppressNotifications();
    }
}
