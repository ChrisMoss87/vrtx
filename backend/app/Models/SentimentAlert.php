<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentimentAlert extends Model
{
    protected $fillable = [
        'record_module',
        'record_id',
        'sentiment_id',
        'alert_type',
        'message',
        'severity',
        'is_read',
        'is_dismissed',
        'dismissed_by',
        'dismissed_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_dismissed' => 'boolean',
        'dismissed_at' => 'datetime',
    ];

    // Alert types
    public const TYPE_NEGATIVE_DETECTED = 'negative_detected';
    public const TYPE_SENTIMENT_DROP = 'sentiment_drop';
    public const TYPE_URGENT_DETECTED = 'urgent_detected';

    // Severities
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';

    public function sentiment(): BelongsTo
    {
        return $this->belongsTo(SentimentScore::class, 'sentiment_id');
    }

    public function dismissedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    /**
     * Mark as read
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Dismiss alert
     */
    public function dismiss(int $userId): void
    {
        $this->update([
            'is_dismissed' => true,
            'dismissed_by' => $userId,
            'dismissed_at' => now(),
        ]);
    }

    /**
     * Scope unread alerts
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false)->where('is_dismissed', false);
    }

    /**
     * Scope by severity
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }
}
