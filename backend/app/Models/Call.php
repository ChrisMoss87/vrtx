<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Call extends Model
{
    use HasFactory;
    protected $fillable = [
        'provider_id',
        'external_call_id',
        'direction',
        'status',
        'from_number',
        'to_number',
        'user_id',
        'contact_id',
        'contact_module',
        'duration_seconds',
        'ring_duration_seconds',
        'started_at',
        'answered_at',
        'ended_at',
        'recording_url',
        'recording_sid',
        'recording_duration_seconds',
        'recording_status',
        'notes',
        'outcome',
        'custom_fields',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'custom_fields' => 'array',
        'metadata' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CallProvider::class, 'provider_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'contact_id');
    }

    public function transcription(): HasOne
    {
        return $this->hasOne(CallTranscription::class);
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeMissed($query)
    {
        return $query->whereIn('status', ['no_answer', 'busy', 'canceled']);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForContact($query, int $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    public function scopeWithRecording($query)
    {
        return $query->whereNotNull('recording_url');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isMissed(): bool
    {
        return in_array($this->status, ['no_answer', 'busy', 'canceled']);
    }

    public function hasRecording(): bool
    {
        return !empty($this->recording_url);
    }

    public function hasTranscription(): bool
    {
        return $this->transcription()->exists();
    }

    public function getFormattedDuration(): string
    {
        if (!$this->duration_seconds) {
            return '0:00';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function markAsCompleted(array $data = []): void
    {
        $this->update(array_merge([
            'status' => 'completed',
            'ended_at' => now(),
        ], $data));
    }

    public function linkToContact(int $contactId, string $module = 'contacts'): void
    {
        $this->update([
            'contact_id' => $contactId,
            'contact_module' => $module,
        ]);
    }

    public function logOutcome(string $outcome, ?string $notes = null): void
    {
        $this->update([
            'outcome' => $outcome,
            'notes' => $notes ?? $this->notes,
        ]);
    }
}
