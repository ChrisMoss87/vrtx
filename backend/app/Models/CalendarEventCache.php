<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventCache extends Model
{
    use HasFactory;

    protected $table = 'calendar_events_cache';

    protected $fillable = [
        'calendar_connection_id',
        'external_event_id',
        'title',
        'start_time',
        'end_time',
        'is_all_day',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_all_day' => 'boolean',
    ];

    /**
     * Event statuses.
     */
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_TENTATIVE = 'tentative';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the calendar connection.
     */
    public function calendarConnection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class);
    }

    /**
     * Scope for events in a date range.
     */
    public function scopeInRange($query, $start, $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->where('start_time', '>=', $start)
                ->where('start_time', '<=', $end);
        })->orWhere(function ($q) use ($start, $end) {
            $q->where('end_time', '>=', $start)
                ->where('end_time', '<=', $end);
        })->orWhere(function ($q) use ($start, $end) {
            $q->where('start_time', '<=', $start)
                ->where('end_time', '>=', $end);
        });
    }

    /**
     * Scope for non-cancelled events.
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    /**
     * Check if event blocks a specific time slot.
     */
    public function blocksTimeSlot(\DateTime $slotStart, \DateTime $slotEnd): bool
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return false;
        }

        // All-day events block the entire day
        if ($this->is_all_day) {
            return $this->start_time->isSameDay($slotStart) || $this->start_time->isSameDay($slotEnd);
        }

        // Check for overlap
        return $slotStart < $this->end_time && $slotEnd > $this->start_time;
    }
}
