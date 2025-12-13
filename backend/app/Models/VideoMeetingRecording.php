<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoMeetingRecording extends Model
{
    protected $fillable = [
        'meeting_id',
        'external_recording_id',
        'type',
        'status',
        'file_url',
        'download_url',
        'play_url',
        'file_size',
        'duration_seconds',
        'format',
        'recording_start',
        'recording_end',
        'expires_at',
        'transcript_text',
        'transcript_segments',
        'metadata',
    ];

    protected $casts = [
        'recording_start' => 'datetime',
        'recording_end' => 'datetime',
        'expires_at' => 'datetime',
        'transcript_segments' => 'array',
        'metadata' => 'array',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(VideoMeeting::class, 'meeting_id');
    }

    public function scopeVideo($query)
    {
        return $query->where('type', 'video');
    }

    public function scopeAudio($query)
    {
        return $query->where('type', 'audio');
    }

    public function scopeTranscript($query)
    {
        return $query->where('type', 'transcript');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function getFileSizeFormatted(): ?string
    {
        if (!$this->file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
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
