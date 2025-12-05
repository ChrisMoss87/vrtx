<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BlueprintTransitionExecution extends Model
{
    use HasFactory;

    // Execution statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PENDING_REQUIREMENTS = 'pending_requirements';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'transition_id',
        'record_id',
        'from_state_id',
        'to_state_id',
        'executed_by',
        'status',
        'requirements_data',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'transition_id' => 'integer',
        'record_id' => 'integer',
        'from_state_id' => 'integer',
        'to_state_id' => 'integer',
        'executed_by' => 'integer',
        'requirements_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * Get the transition.
     */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(BlueprintTransition::class, 'transition_id');
    }

    /**
     * Get the source state.
     */
    public function fromState(): BelongsTo
    {
        return $this->belongsTo(BlueprintState::class, 'from_state_id');
    }

    /**
     * Get the destination state.
     */
    public function toState(): BelongsTo
    {
        return $this->belongsTo(BlueprintState::class, 'to_state_id');
    }

    /**
     * Get the user who executed the transition.
     */
    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /**
     * Get action logs.
     */
    public function actionLogs(): HasMany
    {
        return $this->hasMany(BlueprintActionLog::class, 'execution_id');
    }

    /**
     * Get approval request.
     */
    public function approvalRequest(): HasOne
    {
        return $this->hasOne(BlueprintApprovalRequest::class, 'execution_id');
    }

    /**
     * Check if execution is pending requirements.
     */
    public function isPendingRequirements(): bool
    {
        return $this->status === self::STATUS_PENDING_REQUIREMENTS;
    }

    /**
     * Check if execution is pending approval.
     */
    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if execution is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if execution is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if execution can proceed (ready for completion).
     */
    public function canComplete(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
        ]);
    }

    /**
     * Get available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PENDING_REQUIREMENTS => 'Pending Requirements',
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by record.
     */
    public function scopeForRecord($query, int $recordId)
    {
        return $query->where('record_id', $recordId);
    }
}
