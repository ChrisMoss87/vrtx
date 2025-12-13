<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallTranscription extends Model
{
    protected $fillable = [
        'call_id',
        'status',
        'full_text',
        'segments',
        'language',
        'confidence',
        'provider',
        'summary',
        'key_points',
        'action_items',
        'sentiment',
        'entities',
        'word_count',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'segments' => 'array',
        'key_points' => 'array',
        'action_items' => 'array',
        'entities' => 'array',
        'processed_at' => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsCompleted(array $data): void
    {
        $this->update(array_merge($data, [
            'status' => 'completed',
            'processed_at' => now(),
        ]));
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    public function getReadingTimeMinutes(): int
    {
        if (!$this->word_count) {
            return 0;
        }

        // Average reading speed: 200 words per minute
        return (int) ceil($this->word_count / 200);
    }

    public function getSpeakers(): array
    {
        if (!$this->segments) {
            return [];
        }

        return array_unique(array_column($this->segments, 'speaker'));
    }

    public function getSegmentsBySpeaker(string $speaker): array
    {
        if (!$this->segments) {
            return [];
        }

        return array_filter($this->segments, fn($s) => ($s['speaker'] ?? '') === $speaker);
    }
}
