<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'dnd_enabled',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'weekend_notifications',
        'timezone',
    ];

    protected $casts = [
        'dnd_enabled' => 'boolean',
        'quiet_hours_enabled' => 'boolean',
        'quiet_hours_start' => 'datetime:H:i',
        'quiet_hours_end' => 'datetime:H:i',
        'weekend_notifications' => 'boolean',
    ];

    protected $attributes = [
        'dnd_enabled' => false,
        'quiet_hours_enabled' => false,
        'weekend_notifications' => true,
        'timezone' => 'UTC',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if notifications should be suppressed right now
     */
    public function shouldSuppressNotifications(): bool
    {
        // Global DND check
        if ($this->dnd_enabled) {
            return true;
        }

        $now = Carbon::now($this->timezone);

        // Weekend check
        if (!$this->weekend_notifications && $now->isWeekend()) {
            return true;
        }

        // Quiet hours check
        if ($this->quiet_hours_enabled && $this->quiet_hours_start && $this->quiet_hours_end) {
            $start = Carbon::createFromTimeString($this->quiet_hours_start->format('H:i'), $this->timezone);
            $end = Carbon::createFromTimeString($this->quiet_hours_end->format('H:i'), $this->timezone);

            // Handle overnight quiet hours (e.g., 22:00 - 08:00)
            if ($start > $end) {
                // Overnight: suppress if after start OR before end
                if ($now->gte($start) || $now->lt($end)) {
                    return true;
                }
            } else {
                // Same day: suppress if between start and end
                if ($now->gte($start) && $now->lt($end)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the next time notifications will be allowed
     */
    public function getNextActiveTime(): ?Carbon
    {
        if (!$this->shouldSuppressNotifications()) {
            return null;
        }

        $now = Carbon::now($this->timezone);

        // If DND is enabled, we don't know when it will end
        if ($this->dnd_enabled) {
            return null;
        }

        // Weekend - return Monday 00:00
        if (!$this->weekend_notifications && $now->isWeekend()) {
            return $now->next(Carbon::MONDAY)->startOfDay();
        }

        // Quiet hours
        if ($this->quiet_hours_enabled && $this->quiet_hours_end) {
            $end = Carbon::createFromTimeString($this->quiet_hours_end->format('H:i'), $this->timezone);

            // If end is before now (overnight scenario), it's tomorrow
            if ($end->lt($now)) {
                $end->addDay();
            }

            return $end;
        }

        return null;
    }
}
