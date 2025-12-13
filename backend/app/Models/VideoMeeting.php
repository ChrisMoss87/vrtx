<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoMeeting extends Model
{
    protected $fillable = [
        'provider_id',
        'external_meeting_id',
        'host_id',
        'title',
        'description',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'duration_minutes',
        'actual_duration_seconds',
        'join_url',
        'host_url',
        'password',
        'waiting_room_enabled',
        'recording_enabled',
        'recording_auto_start',
        'recording_url',
        'recording_status',
        'meeting_type',
        'recurrence_type',
        'recurrence_settings',
        'deal_id',
        'deal_module',
        'custom_fields',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'waiting_room_enabled' => 'boolean',
        'recording_enabled' => 'boolean',
        'recording_auto_start' => 'boolean',
        'recurrence_settings' => 'array',
        'custom_fields' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'password',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(VideoProvider::class, 'provider_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(VideoMeetingParticipant::class, 'meeting_id');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(VideoMeetingRecording::class, 'meeting_id');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeStarted($query)
    {
        return $query->where('status', 'started');
    }

    public function scopeEnded($query)
    {
        return $query->where('status', 'ended');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '>', now());
    }

    public function scopeForHost($query, int $hostId)
    {
        return $query->where('host_id', $hostId);
    }

    public function scopeForDeal($query, int $dealId, string $module)
    {
        return $query->where('deal_id', $dealId)
            ->where('deal_module', $module);
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isStarted(): bool
    {
        return $this->status === 'started';
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function getActualDurationFormatted(): ?string
    {
        if (!$this->actual_duration_seconds) {
            return null;
        }

        $hours = floor($this->actual_duration_seconds / 3600);
        $minutes = floor(($this->actual_duration_seconds % 3600) / 60);
        $seconds = $this->actual_duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getParticipantCount(): int
    {
        return $this->participants()->count();
    }

    public function getJoinedParticipantCount(): int
    {
        return $this->participants()->where('status', 'joined')->count();
    }
}
