<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Integration\ValueObjects\SyncDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSyncLog extends Model
{
    protected $fillable = [
        'connection_id',
        'direction',
        'entity_type',
        'action',
        'records_processed',
        'records_created',
        'records_updated',
        'records_skipped',
        'records_failed',
        'errors',
        'summary',
        'started_at',
        'completed_at',
        'duration_ms',
        'status',
    ];

    protected $casts = [
        'errors' => 'array',
        'summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'direction' => SyncDirection::class,
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function complete(array $summary = []): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_ms' => now()->diffInMilliseconds($this->started_at),
            'summary' => $summary,
        ]);
    }

    public function fail(string $error, array $errors = []): void
    {
        $allErrors = $this->errors ?? [];
        $allErrors[] = $error;

        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'duration_ms' => now()->diffInMilliseconds($this->started_at),
            'errors' => array_merge($allErrors, $errors),
        ]);
    }

    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('records_processed', $count);
    }

    public function incrementCreated(int $count = 1): void
    {
        $this->increment('records_created', $count);
    }

    public function incrementUpdated(int $count = 1): void
    {
        $this->increment('records_updated', $count);
    }

    public function incrementSkipped(int $count = 1): void
    {
        $this->increment('records_skipped', $count);
    }

    public function incrementFailed(int $count = 1): void
    {
        $this->increment('records_failed', $count);
    }

    public function addError(string $error): void
    {
        $errors = $this->errors ?? [];
        $errors[] = $error;
        $this->update(['errors' => $errors]);
    }
}
