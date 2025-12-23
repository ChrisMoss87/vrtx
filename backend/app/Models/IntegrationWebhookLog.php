<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationWebhookLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'webhook_id',
        'event_type',
        'event_id',
        'payload',
        'headers',
        'status',
        'error_message',
        'processing_time_ms',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(IntegrationWebhook::class, 'webhook_id');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['received', 'processing']);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'processing_time_ms' => now()->diffInMilliseconds($this->received_at),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'processed_at' => now(),
            'processing_time_ms' => now()->diffInMilliseconds($this->received_at),
        ]);
    }

    public function markIgnored(): void
    {
        $this->update([
            'status' => 'ignored',
            'processed_at' => now(),
        ]);
    }
}
