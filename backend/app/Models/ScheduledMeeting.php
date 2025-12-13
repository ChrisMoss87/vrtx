<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ScheduledMeeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_type_id',
        'host_user_id',
        'contact_id',
        'attendee_name',
        'attendee_email',
        'attendee_phone',
        'start_time',
        'end_time',
        'timezone',
        'location',
        'notes',
        'answers',
        'status',
        'calendar_event_id',
        'manage_token',
        'reminder_sent',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'answers' => 'array',
        'reminder_sent' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Meeting statuses.
     */
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RESCHEDULED = 'rescheduled';
    public const STATUS_NO_SHOW = 'no_show';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($meeting) {
            if (empty($meeting->manage_token)) {
                $meeting->manage_token = Str::random(64);
            }
        });
    }

    /**
     * Get the meeting type.
     */
    public function meetingType(): BelongsTo
    {
        return $this->belongsTo(MeetingType::class);
    }

    /**
     * Get the host user.
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /**
     * Get the contact record if linked.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'contact_id');
    }

    /**
     * Get the manage/reschedule URL.
     */
    public function getManageUrlAttribute(): string
    {
        return url("/schedule/manage/{$this->manage_token}");
    }

    /**
     * Get the cancel URL.
     */
    public function getCancelUrlAttribute(): string
    {
        return url("/schedule/cancel/{$this->manage_token}");
    }

    /**
     * Check if meeting is upcoming.
     */
    public function getIsUpcomingAttribute(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->start_time->isFuture();
    }

    /**
     * Check if meeting can be cancelled.
     */
    public function getCanCancelAttribute(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->start_time->isFuture();
    }

    /**
     * Check if meeting can be rescheduled.
     */
    public function getCanRescheduleAttribute(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->start_time->isFuture();
    }

    /**
     * Get duration in minutes.
     */
    public function getDurationMinutesAttribute(): int
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    /**
     * Cancel the meeting.
     */
    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Mark meeting as completed.
     */
    public function markCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    /**
     * Mark meeting as no-show.
     */
    public function markNoShow(): void
    {
        $this->update(['status' => self::STATUS_NO_SHOW]);
    }

    /**
     * Scope for scheduled meetings.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope for upcoming meetings.
     */
    public function scopeUpcoming($query)
    {
        return $query->scheduled()->where('start_time', '>', now());
    }

    /**
     * Scope for meetings in a date range.
     */
    public function scopeInRange($query, $start, $end)
    {
        return $query->where('start_time', '>=', $start)
            ->where('start_time', '<=', $end);
    }

    /**
     * Scope for a specific host.
     */
    public function scopeForHost($query, $userId)
    {
        return $query->where('host_user_id', $userId);
    }

    /**
     * Scope for meetings needing reminders.
     */
    public function scopeNeedingReminder($query, $hoursBeforeMeeting = 24)
    {
        $reminderTime = now()->addHours($hoursBeforeMeeting);

        return $query->scheduled()
            ->where('reminder_sent', false)
            ->where('start_time', '<=', $reminderTime)
            ->where('start_time', '>', now());
    }
}
