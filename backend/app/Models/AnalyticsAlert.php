<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsAlert extends Model
{
    use HasFactory;

    // Alert types
    public const TYPE_THRESHOLD = 'threshold';
    public const TYPE_ANOMALY = 'anomaly';
    public const TYPE_TREND = 'trend';
    public const TYPE_COMPARISON = 'comparison';

    // Check frequencies
    public const FREQUENCY_REALTIME = 'realtime';
    public const FREQUENCY_HOURLY = 'hourly';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';

    // Notification frequencies
    public const NOTIFY_IMMEDIATE = 'immediate';
    public const NOTIFY_DAILY_DIGEST = 'daily_digest';
    public const NOTIFY_WEEKLY_DIGEST = 'weekly_digest';

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'alert_type',
        'module_id',
        'report_id',
        'metric_field',
        'aggregation',
        'filters',
        'condition_config',
        'notification_config',
        'check_frequency',
        'check_time',
        'is_active',
        'last_checked_at',
        'last_triggered_at',
        'trigger_count',
        'consecutive_triggers',
        'cooldown_minutes',
        'cooldown_until',
    ];

    protected $casts = [
        'filters' => 'array',
        'condition_config' => 'array',
        'notification_config' => 'array',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_triggered_at' => 'datetime',
        'cooldown_until' => 'datetime',
        'check_time' => 'datetime:H:i',
    ];

    protected $attributes = [
        'aggregation' => 'count',
        'check_frequency' => 'hourly',
        'is_active' => true,
        'trigger_count' => 0,
        'consecutive_triggers' => 0,
        'cooldown_minutes' => 60,
    ];

    /**
     * Get the user who created this alert.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the module this alert monitors.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the report this alert monitors.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Get the alert history entries.
     */
    public function history(): HasMany
    {
        return $this->hasMany(AnalyticsAlertHistory::class, 'alert_id');
    }

    /**
     * Get the alert subscriptions.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(AnalyticsAlertSubscription::class, 'alert_id');
    }

    /**
     * Scope to active alerts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to alerts due for checking.
     */
    public function scopeDueForCheck($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('last_checked_at')
                    ->orWhere(function ($q2) {
                        // Hourly alerts not checked in last hour
                        $q2->where('check_frequency', self::FREQUENCY_HOURLY)
                           ->where('last_checked_at', '<', now()->subHour());
                    })
                    ->orWhere(function ($q2) {
                        // Daily alerts not checked today
                        $q2->where('check_frequency', self::FREQUENCY_DAILY)
                           ->where('last_checked_at', '<', now()->startOfDay());
                    })
                    ->orWhere(function ($q2) {
                        // Weekly alerts not checked this week
                        $q2->where('check_frequency', self::FREQUENCY_WEEKLY)
                           ->where('last_checked_at', '<', now()->startOfWeek());
                    });
            });
    }

    /**
     * Scope by alert type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('alert_type', $type);
    }

    /**
     * Check if alert is in cooldown period.
     */
    public function isInCooldown(): bool
    {
        return $this->cooldown_until && $this->cooldown_until->isFuture();
    }

    /**
     * Start cooldown period.
     */
    public function startCooldown(): void
    {
        $this->update([
            'cooldown_until' => now()->addMinutes($this->cooldown_minutes),
        ]);
    }

    /**
     * Record a trigger event.
     */
    public function recordTrigger(): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'trigger_count' => $this->trigger_count + 1,
            'consecutive_triggers' => $this->consecutive_triggers + 1,
        ]);

        $this->startCooldown();
    }

    /**
     * Record a check with no trigger (reset consecutive counter).
     */
    public function recordCheck(): void
    {
        $this->update([
            'last_checked_at' => now(),
            'consecutive_triggers' => 0,
        ]);
    }

    /**
     * Get notification recipients (user IDs).
     */
    public function getRecipientIds(): array
    {
        $config = $this->notification_config ?? [];
        $recipients = $config['recipients'] ?? [];

        // Always include the alert creator
        if (!in_array($this->user_id, $recipients)) {
            $recipients[] = $this->user_id;
        }

        return $recipients;
    }

    /**
     * Get notification channels.
     */
    public function getChannels(): array
    {
        return $this->notification_config['channels'] ?? ['in_app'];
    }

    /**
     * Get available alert types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_THRESHOLD => 'Threshold Alert',
            self::TYPE_ANOMALY => 'Anomaly Detection',
            self::TYPE_TREND => 'Trend Alert',
            self::TYPE_COMPARISON => 'Period Comparison',
        ];
    }

    /**
     * Get available check frequencies.
     */
    public static function getFrequencies(): array
    {
        return [
            self::FREQUENCY_HOURLY => 'Every Hour',
            self::FREQUENCY_DAILY => 'Daily',
            self::FREQUENCY_WEEKLY => 'Weekly',
        ];
    }
}
