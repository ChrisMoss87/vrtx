<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RottingAlert extends Model
{
    use HasFactory;

    // Alert types based on severity
    public const TYPE_WARNING = 'warning';   // 50-75% of threshold
    public const TYPE_STALE = 'stale';       // 75-100% of threshold
    public const TYPE_ROTTING = 'rotting';   // >100% of threshold

    protected $fillable = [
        'module_record_id',
        'stage_id',
        'user_id',
        'alert_type',
        'days_inactive',
        'sent_at',
        'acknowledged',
        'acknowledged_at',
    ];

    protected $casts = [
        'module_record_id' => 'integer',
        'stage_id' => 'integer',
        'user_id' => 'integer',
        'days_inactive' => 'integer',
        'sent_at' => 'datetime',
        'acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'acknowledged' => false,
    ];

    /**
     * Get the module record this alert is for.
     */
    public function moduleRecord(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class);
    }

    /**
     * Get the stage this alert is for.
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Get the user this alert was sent to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get unacknowledged alerts.
     */
    public function scopeUnacknowledged($query)
    {
        return $query->where('acknowledged', false);
    }

    /**
     * Scope to get alerts for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get alerts by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('alert_type', $type);
    }

    /**
     * Scope to get recent alerts (last N days).
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('sent_at', '>=', now()->subDays($days));
    }

    /**
     * Mark the alert as acknowledged.
     */
    public function acknowledge(): bool
    {
        return $this->update([
            'acknowledged' => true,
            'acknowledged_at' => now(),
        ]);
    }

    /**
     * Get severity level (1-3) based on alert type.
     */
    public function getSeverityAttribute(): int
    {
        return match ($this->alert_type) {
            self::TYPE_WARNING => 1,
            self::TYPE_STALE => 2,
            self::TYPE_ROTTING => 3,
            default => 0,
        };
    }

    /**
     * Get the appropriate CSS color class for this alert type.
     */
    public function getColorClassAttribute(): string
    {
        return match ($this->alert_type) {
            self::TYPE_WARNING => 'yellow',
            self::TYPE_STALE => 'orange',
            self::TYPE_ROTTING => 'red',
            default => 'gray',
        };
    }
}
