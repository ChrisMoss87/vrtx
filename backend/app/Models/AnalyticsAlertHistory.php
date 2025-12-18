<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsAlertHistory extends Model
{
    use HasFactory;

    // Status values
    public const STATUS_TRIGGERED = 'triggered';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_MUTED = 'muted';

    protected $table = 'analytics_alert_history';

    protected $fillable = [
        'alert_id',
        'status',
        'metric_value',
        'threshold_value',
        'baseline_value',
        'deviation_percent',
        'context',
        'message',
        'acknowledged_by',
        'acknowledged_at',
        'acknowledgment_note',
        'notifications_sent',
    ];

    protected $casts = [
        'metric_value' => 'decimal:4',
        'threshold_value' => 'decimal:4',
        'baseline_value' => 'decimal:4',
        'deviation_percent' => 'decimal:2',
        'context' => 'array',
        'notifications_sent' => 'array',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * Get the alert this history belongs to.
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(AnalyticsAlert::class, 'alert_id');
    }

    /**
     * Get the user who acknowledged this alert.
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Scope to triggered entries.
     */
    public function scopeTriggered($query)
    {
        return $query->where('status', self::STATUS_TRIGGERED);
    }

    /**
     * Scope to unacknowledged entries.
     */
    public function scopeUnacknowledged($query)
    {
        return $query->whereIn('status', [self::STATUS_TRIGGERED])
                     ->whereNull('acknowledged_at');
    }

    /**
     * Acknowledge this alert history entry.
     */
    public function acknowledge(int $userId, ?string $note = null): void
    {
        $this->update([
            'status' => self::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
            'acknowledgment_note' => $note,
        ]);
    }

    /**
     * Mark as resolved.
     */
    public function resolve(): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
        ]);
    }

    /**
     * Get a formatted message for this alert.
     */
    public function getFormattedMessage(): string
    {
        if ($this->message) {
            return $this->message;
        }

        $alert = $this->alert;
        $value = number_format($this->metric_value ?? 0, 2);

        return match ($alert->alert_type) {
            AnalyticsAlert::TYPE_THRESHOLD => "Metric value ({$value}) crossed threshold ({$this->threshold_value})",
            AnalyticsAlert::TYPE_ANOMALY => "Anomaly detected: {$this->deviation_percent}% deviation from baseline",
            AnalyticsAlert::TYPE_TREND => "Trend alert: metric is trending in the monitored direction",
            AnalyticsAlert::TYPE_COMPARISON => "Period comparison: {$this->deviation_percent}% change detected",
            default => "Alert triggered with value: {$value}",
        };
    }
}
