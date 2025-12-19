<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CadenceStepExecution extends Model
{
    use HasFactory, BelongsToTenant;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_EXECUTING = 'executing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_CANCELLED = 'cancelled';

    public const RESULT_SENT = 'sent';
    public const RESULT_DELIVERED = 'delivered';
    public const RESULT_OPENED = 'opened';
    public const RESULT_CLICKED = 'clicked';
    public const RESULT_REPLIED = 'replied';
    public const RESULT_BOUNCED = 'bounced';
    public const RESULT_FAILED = 'failed';
    public const RESULT_COMPLETED = 'completed';
    public const RESULT_SKIPPED = 'skipped';

    protected $fillable = [
        'enrollment_id',
        'step_id',
        'scheduled_at',
        'executed_at',
        'status',
        'result',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'executed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CadenceEnrollment::class, 'enrollment_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(CadenceStep::class, 'step_id');
    }

    public function markAsExecuting(): void
    {
        $this->update([
            'status' => self::STATUS_EXECUTING,
        ]);
    }

    public function markAsCompleted(string $result, array $metadata = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'executed_at' => now(),
            'result' => $result,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    public function markAsFailed(string $errorMessage, array $metadata = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'executed_at' => now(),
            'result' => self::RESULT_FAILED,
            'error_message' => $errorMessage,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    public function markAsSkipped(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'executed_at' => now(),
            'result' => self::RESULT_SKIPPED,
            'error_message' => $reason,
        ]);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeDue($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now());
    }
}
