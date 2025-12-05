<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'status',
        'attempts',
        'response_code',
        'response_body',
        'error_message',
        'response_time_ms',
        'delivered_at',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'response_code' => 'integer',
        'response_time_ms' => 'integer',
        'delivered_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    /**
     * Get the webhook this delivery belongs to.
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * Check if delivery can be retried.
     */
    public function canRetry(): bool
    {
        if ($this->status !== self::STATUS_FAILED) {
            return false;
        }

        $webhook = $this->webhook;
        return $this->attempts < $webhook->retry_count;
    }

    /**
     * Mark as pending retry.
     */
    public function scheduleRetry(): void
    {
        $webhook = $this->webhook;
        $delay = $webhook->retry_delay * pow(2, $this->attempts - 1); // Exponential backoff

        $this->update([
            'status' => self::STATUS_PENDING,
            'next_retry_at' => now()->addSeconds($delay),
        ]);
    }

    /**
     * Mark as successful.
     */
    public function markAsSuccess(int $responseCode, ?string $responseBody, int $responseTimeMs): void
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'response_code' => $responseCode,
            'response_body' => $responseBody ? substr($responseBody, 0, 10000) : null,
            'response_time_ms' => $responseTimeMs,
            'delivered_at' => now(),
            'next_retry_at' => null,
        ]);

        $this->webhook->recordSuccess();
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(int $attempts, ?int $responseCode, ?string $errorMessage, ?int $responseTimeMs = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'attempts' => $attempts,
            'response_code' => $responseCode,
            'error_message' => $errorMessage ? substr($errorMessage, 0, 1000) : null,
            'response_time_ms' => $responseTimeMs,
        ]);

        if (!$this->canRetry()) {
            $this->webhook->recordFailure();
        } else {
            $this->scheduleRetry();
        }
    }

    /**
     * Scope to pending deliveries ready for retry.
     */
    public function scopeReadyForRetry($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    /**
     * Scope to failed deliveries.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to recent deliveries.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
