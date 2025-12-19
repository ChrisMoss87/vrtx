<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_available' => 'boolean',
    ];

    /**
     * Day names for reference.
     */
    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * Default business hours availability.
     */
    public static function getDefaultRules(): array
    {
        return [
            ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00', 'is_available' => true], // Monday
            ['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00', 'is_available' => true], // Tuesday
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'is_available' => true], // Wednesday
            ['day_of_week' => 4, 'start_time' => '09:00', 'end_time' => '17:00', 'is_available' => true], // Thursday
            ['day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '17:00', 'is_available' => true], // Friday
        ];
    }

    /**
     * Get the user who owns this rule.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the day name.
     */
    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }

    /**
     * Check if a given time falls within this availability window.
     */
    public function containsTime(string $time): bool
    {
        if (!$this->is_available) {
            return false;
        }

        return $time >= $this->start_time && $time < $this->end_time;
    }

    /**
     * Get the duration of this availability window in minutes.
     */
    public function getDurationMinutesAttribute(): int
    {
        $start = strtotime($this->start_time);
        $end = strtotime($this->end_time);
        return ($end - $start) / 60;
    }
}
