<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Notification;

use App\Domain\Notification\Repositories\NotificationScheduleRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentNotificationScheduleRepository implements NotificationScheduleRepositoryInterface
{
    private const TABLE = 'notification_schedules';

    public function getOrCreateForUser(int $userId): array
    {
        $row = DB::table(self::TABLE)->where('user_id', $userId)->first();

        if (!$row) {
            $id = DB::table(self::TABLE)->insertGetId([
                'user_id' => $userId,
                'timezone' => 'UTC',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table(self::TABLE)->where('id', $id)->first();
        }

        return $this->toArray($row);
    }

    public function update(int $userId, array $settings): array
    {
        $row = DB::table(self::TABLE)->where('user_id', $userId)->first();

        if ($row) {
            DB::table(self::TABLE)
                ->where('user_id', $userId)
                ->update(array_merge($settings, ['updated_at' => now()]));
        } else {
            DB::table(self::TABLE)->insert(array_merge($settings, [
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $row = DB::table(self::TABLE)->where('user_id', $userId)->first();

        return $this->toArray($row);
    }

    public function shouldSuppressNotifications(int $userId): bool
    {
        $row = DB::table(self::TABLE)->where('user_id', $userId)->first();

        if (!$row) {
            return false;
        }

        return $this->checkShouldSuppressNotifications($row);
    }

    private function checkShouldSuppressNotifications(stdClass $row): bool
    {
        // Check quiet hours
        if ($row->quiet_hours_enabled ?? false) {
            $timezone = $row->timezone ?? 'UTC';
            $now = Carbon::now($timezone);
            $currentTime = $now->format('H:i');

            $quietStart = $row->quiet_hours_start ?? null;
            $quietEnd = $row->quiet_hours_end ?? null;

            if ($quietStart && $quietEnd) {
                // Handle overnight quiet hours (e.g., 22:00 - 07:00)
                if ($quietStart > $quietEnd) {
                    if ($currentTime >= $quietStart || $currentTime <= $quietEnd) {
                        return true;
                    }
                } else {
                    if ($currentTime >= $quietStart && $currentTime <= $quietEnd) {
                        return true;
                    }
                }
            }
        }

        // Check weekend suppression
        if ($row->suppress_weekends ?? false) {
            $timezone = $row->timezone ?? 'UTC';
            $now = Carbon::now($timezone);

            if ($now->isWeekend()) {
                return true;
            }
        }

        return false;
    }

    private function toArray(stdClass $row): array
    {
        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'timezone' => $row->timezone ?? 'UTC',
            'quiet_hours_enabled' => (bool) ($row->quiet_hours_enabled ?? false),
            'quiet_hours_start' => $row->quiet_hours_start ?? null,
            'quiet_hours_end' => $row->quiet_hours_end ?? null,
            'suppress_weekends' => (bool) ($row->suppress_weekends ?? false),
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
