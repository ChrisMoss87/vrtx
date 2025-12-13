<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MeetingType extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduling_page_id',
        'name',
        'slug',
        'duration_minutes',
        'description',
        'location_type',
        'location_details',
        'color',
        'is_active',
        'questions',
        'settings',
        'display_order',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'questions' => 'array',
        'settings' => 'array',
        'display_order' => 'integer',
    ];

    /**
     * Default settings for a meeting type.
     */
    public const DEFAULT_SETTINGS = [
        'buffer_before' => 0,       // minutes before meeting
        'buffer_after' => 15,       // minutes after meeting
        'min_notice_hours' => 4,    // minimum hours notice required
        'max_days_advance' => 60,   // how far in advance can book
        'slot_interval' => 30,      // interval between slots in minutes
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($type) {
            if (empty($type->slug)) {
                $type->slug = Str::slug($type->name);
            }
            if (empty($type->settings)) {
                $type->settings = self::DEFAULT_SETTINGS;
            }
        });
    }

    /**
     * Get the scheduling page this type belongs to.
     */
    public function schedulingPage(): BelongsTo
    {
        return $this->belongsTo(SchedulingPage::class);
    }

    /**
     * Get the scheduled meetings for this type.
     */
    public function scheduledMeetings(): HasMany
    {
        return $this->hasMany(ScheduledMeeting::class);
    }

    /**
     * Get a specific setting with fallback to default.
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? [];
        return $settings[$key] ?? self::DEFAULT_SETTINGS[$key] ?? $default;
    }

    /**
     * Get the buffer time before meetings in minutes.
     */
    public function getBufferBeforeAttribute(): int
    {
        return $this->getSetting('buffer_before', 0);
    }

    /**
     * Get the buffer time after meetings in minutes.
     */
    public function getBufferAfterAttribute(): int
    {
        return $this->getSetting('buffer_after', 15);
    }

    /**
     * Get the minimum notice required in hours.
     */
    public function getMinNoticeHoursAttribute(): int
    {
        return $this->getSetting('min_notice_hours', 4);
    }

    /**
     * Get how many days in advance bookings are allowed.
     */
    public function getMaxDaysAdvanceAttribute(): int
    {
        return $this->getSetting('max_days_advance', 60);
    }

    /**
     * Get the slot interval in minutes.
     */
    public function getSlotIntervalAttribute(): int
    {
        return $this->getSetting('slot_interval', 30);
    }

    /**
     * Get the total time blocked including buffers.
     */
    public function getTotalBlockedMinutesAttribute(): int
    {
        return $this->buffer_before + $this->duration_minutes + $this->buffer_after;
    }

    /**
     * Get the public URL for this meeting type.
     */
    public function getPublicUrlAttribute(): string
    {
        return url("/schedule/{$this->schedulingPage->slug}/{$this->slug}");
    }

    /**
     * Scope for active meeting types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
