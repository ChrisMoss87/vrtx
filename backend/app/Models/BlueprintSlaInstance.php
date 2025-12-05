<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlueprintSlaInstance extends Model
{
    use HasFactory;

    protected $table = 'blueprint_sla_instances';

    // Instance statuses
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_BREACHED = 'breached';

    protected $fillable = [
        'sla_id',
        'record_id',
        'state_entered_at',
        'due_at',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'sla_id' => 'integer',
        'record_id' => 'integer',
        'state_entered_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * Get the SLA configuration.
     */
    public function sla(): BelongsTo
    {
        return $this->belongsTo(BlueprintSla::class, 'sla_id');
    }

    /**
     * Get escalation logs.
     */
    public function escalationLogs(): HasMany
    {
        return $this->hasMany(BlueprintSlaEscalationLog::class, 'sla_instance_id');
    }

    /**
     * Check if SLA is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if SLA is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if SLA is breached.
     */
    public function isBreached(): bool
    {
        return $this->status === self::STATUS_BREACHED;
    }

    /**
     * Get percentage of time elapsed.
     */
    public function getPercentageElapsed(): float
    {
        if (!$this->state_entered_at || !$this->due_at) {
            return 0;
        }

        $totalSeconds = $this->due_at->diffInSeconds($this->state_entered_at);
        if ($totalSeconds <= 0) {
            return 100;
        }

        $elapsedSeconds = now()->diffInSeconds($this->state_entered_at);
        return min(100, ($elapsedSeconds / $totalSeconds) * 100);
    }

    /**
     * Get remaining time in seconds.
     */
    public function getRemainingSeconds(): int
    {
        if (!$this->due_at) {
            return 0;
        }

        $remaining = now()->diffInSeconds($this->due_at, false);
        return max(0, $remaining);
    }

    /**
     * Get remaining time in hours.
     */
    public function getRemainingHours(): float
    {
        return $this->getRemainingSeconds() / 3600;
    }

    /**
     * Check if SLA is approaching (based on percentage).
     */
    public function isApproaching(int $threshold = 80): bool
    {
        return $this->isActive() && $this->getPercentageElapsed() >= $threshold;
    }

    /**
     * Mark as completed.
     */
    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as breached.
     */
    public function breach(): void
    {
        $this->update([
            'status' => self::STATUS_BREACHED,
        ]);
    }

    /**
     * Check if an escalation has already been triggered.
     */
    public function hasEscalationTriggered(int $escalationId): bool
    {
        return $this->escalationLogs()
            ->where('escalation_id', $escalationId)
            ->exists();
    }

    /**
     * Get available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_BREACHED => 'Breached',
        ];
    }

    /**
     * Scope to find active instances.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to find overdue instances.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('due_at', '<', now());
    }
}
