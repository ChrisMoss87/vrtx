<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CadenceEnrollment extends Model
{
    use HasFactory, BelongsToTenant;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUS_MEETING_BOOKED = 'meeting_booked';
    public const STATUS_MANUALLY_REMOVED = 'manually_removed';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_PAUSED => 'Paused',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_REPLIED => 'Replied',
        self::STATUS_BOUNCED => 'Bounced',
        self::STATUS_UNSUBSCRIBED => 'Unsubscribed',
        self::STATUS_MEETING_BOOKED => 'Meeting Booked',
        self::STATUS_MANUALLY_REMOVED => 'Manually Removed',
    ];

    protected $fillable = [
        'cadence_id',
        'record_id',
        'current_step_id',
        'status',
        'enrolled_at',
        'next_step_at',
        'completed_at',
        'paused_at',
        'exit_reason',
        'enrolled_by',
        'metadata',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'next_step_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function cadence(): BelongsTo
    {
        return $this->belongsTo(Cadence::class);
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(CadenceStep::class, 'current_step_id');
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(CadenceStepExecution::class, 'enrollment_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canPause(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canResume(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function pause(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_PAUSED,
            'paused_at' => now(),
            'exit_reason' => $reason,
        ]);
    }

    public function resume(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'paused_at' => null,
        ]);
    }

    public function complete(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'exit_reason' => $reason ?? 'Sequence completed',
        ]);
    }

    public function exitWithReason(string $status, string $reason): void
    {
        $this->update([
            'status' => $status,
            'completed_at' => now(),
            'exit_reason' => $reason,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDue($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('next_step_at', '<=', now());
    }
}
