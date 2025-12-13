<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoMeetingParticipant extends Model
{
    protected $fillable = [
        'meeting_id',
        'user_id',
        'email',
        'name',
        'role',
        'status',
        'joined_at',
        'left_at',
        'duration_seconds',
        'device_type',
        'ip_address',
        'location',
        'audio_enabled',
        'video_enabled',
        'screen_shared',
        'attentiveness_score',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'audio_enabled' => 'boolean',
        'video_enabled' => 'boolean',
        'screen_shared' => 'boolean',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(VideoMeeting::class, 'meeting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeHost($query)
    {
        return $query->where('role', 'host');
    }

    public function scopeAttendees($query)
    {
        return $query->where('role', 'attendee');
    }

    public function scopeJoined($query)
    {
        return $query->where('status', 'joined');
    }

    public function scopeNoShow($query)
    {
        return $query->where('status', 'no_show');
    }

    public function isHost(): bool
    {
        return $this->role === 'host';
    }

    public function isCoHost(): bool
    {
        return $this->role === 'co-host';
    }

    public function isAttendee(): bool
    {
        return $this->role === 'attendee';
    }

    public function hasJoined(): bool
    {
        return $this->status === 'joined' || $this->status === 'left';
    }

    public function isNoShow(): bool
    {
        return $this->status === 'no_show';
    }

    public function getDurationFormatted(): ?string
    {
        if (!$this->duration_seconds) {
            return null;
        }

        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
