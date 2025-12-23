<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidgetAlertHistory extends Model
{
    use HasFactory;

    protected $table = 'dashboard_widget_alert_history';

    protected $fillable = [
        'alert_id',
        'triggered_value',
        'threshold_value',
        'context',
        'status',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected $casts = [
        'triggered_value' => 'decimal:4',
        'threshold_value' => 'decimal:4',
        'context' => 'array',
        'acknowledged_at' => 'datetime',
    ];

    // Status values
    public const STATUS_TRIGGERED = 'triggered';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_DISMISSED = 'dismissed';

    public function alert(): BelongsTo
    {
        return $this->belongsTo(DashboardWidgetAlert::class, 'alert_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->where('status', self::STATUS_TRIGGERED);
    }

    public function scopeForAlert($query, int $alertId)
    {
        return $query->where('alert_id', $alertId);
    }

    /**
     * Acknowledge this alert history entry
     */
    public function acknowledge(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
        ]);
    }

    /**
     * Dismiss this alert history entry
     */
    public function dismiss(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_DISMISSED,
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
        ]);
    }

    /**
     * Check if this entry is pending action
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_TRIGGERED;
    }
}
