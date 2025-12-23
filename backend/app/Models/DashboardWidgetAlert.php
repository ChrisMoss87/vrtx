<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardWidgetAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'widget_id',
        'user_id',
        'name',
        'condition_type',
        'threshold_value',
        'comparison_period',
        'severity',
        'notification_channels',
        'cooldown_minutes',
        'is_active',
        'last_triggered_at',
        'trigger_count',
    ];

    protected $casts = [
        'threshold_value' => 'decimal:4',
        'notification_channels' => 'array',
        'cooldown_minutes' => 'integer',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
    ];

    // Condition types
    public const CONDITION_ABOVE = 'above';
    public const CONDITION_BELOW = 'below';
    public const CONDITION_PERCENT_CHANGE = 'percent_change';
    public const CONDITION_EQUALS = 'equals';

    // Severity levels
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    // Notification channels
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_EMAIL = 'email';

    public function widget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class, 'widget_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(DashboardWidgetAlertHistory::class, 'alert_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForWidget($query, int $widgetId)
    {
        return $query->where('widget_id', $widgetId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if the alert is in cooldown period
     */
    public function isInCooldown(): bool
    {
        if (! $this->last_triggered_at) {
            return false;
        }

        return $this->last_triggered_at->addMinutes($this->cooldown_minutes)->isFuture();
    }

    /**
     * Check if the condition is met for the given value
     */
    public function checkCondition(float $currentValue, ?float $previousValue = null): bool
    {
        return match ($this->condition_type) {
            self::CONDITION_ABOVE => $currentValue > (float) $this->threshold_value,
            self::CONDITION_BELOW => $currentValue < (float) $this->threshold_value,
            self::CONDITION_EQUALS => abs($currentValue - (float) $this->threshold_value) < 0.0001,
            self::CONDITION_PERCENT_CHANGE => $this->checkPercentChange($currentValue, $previousValue),
            default => false,
        };
    }

    /**
     * Check percent change condition
     */
    protected function checkPercentChange(float $currentValue, ?float $previousValue): bool
    {
        if ($previousValue === null || $previousValue === 0.0) {
            return false;
        }

        $percentChange = (($currentValue - $previousValue) / abs($previousValue)) * 100;

        // For percent change, threshold can be positive (increase) or negative (decrease)
        if ((float) $this->threshold_value >= 0) {
            return $percentChange >= (float) $this->threshold_value;
        }

        return $percentChange <= (float) $this->threshold_value;
    }

    /**
     * Record a trigger event
     */
    public function recordTrigger(float $value, array $context = []): DashboardWidgetAlertHistory
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);

        return $this->history()->create([
            'triggered_value' => $value,
            'threshold_value' => $this->threshold_value,
            'context' => $context,
            'status' => 'triggered',
        ]);
    }

    /**
     * Get available condition types
     */
    public static function getConditionTypes(): array
    {
        return [
            self::CONDITION_ABOVE => 'Above threshold',
            self::CONDITION_BELOW => 'Below threshold',
            self::CONDITION_PERCENT_CHANGE => 'Percent change',
            self::CONDITION_EQUALS => 'Equals value',
        ];
    }

    /**
     * Get available severity levels
     */
    public static function getSeverityLevels(): array
    {
        return [
            self::SEVERITY_INFO => 'Info',
            self::SEVERITY_WARNING => 'Warning',
            self::SEVERITY_CRITICAL => 'Critical',
        ];
    }
}
