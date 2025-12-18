<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueprintApprovalRequest extends Model
{
    use HasFactory;

    // Request statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'approval_id',
        'record_id',
        'execution_id',
        'requested_by',
        'original_approver_id',
        'delegation_id',
        'status',
        'responded_by',
        'responded_at',
        'comments',
        'reminder_count',
        'last_reminder_at',
        'escalated_at',
        'escalated_from_id',
    ];

    protected $casts = [
        'approval_id' => 'integer',
        'record_id' => 'integer',
        'execution_id' => 'integer',
        'requested_by' => 'integer',
        'original_approver_id' => 'integer',
        'delegation_id' => 'integer',
        'responded_by' => 'integer',
        'responded_at' => 'datetime',
        'reminder_count' => 'integer',
        'last_reminder_at' => 'datetime',
        'escalated_at' => 'datetime',
        'escalated_from_id' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * Get the approval configuration.
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(BlueprintApproval::class, 'approval_id');
    }

    /**
     * Get the transition execution.
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(BlueprintTransitionExecution::class, 'execution_id');
    }

    /**
     * Get the user who requested the approval.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who responded to the approval.
     */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Get the original approver (before delegation).
     */
    public function originalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_approver_id');
    }

    /**
     * Get the delegation used for this request.
     */
    public function delegation(): BelongsTo
    {
        return $this->belongsTo(ApprovalDelegation::class, 'delegation_id');
    }

    /**
     * Get the user this was escalated from.
     */
    public function escalatedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_from_id');
    }

    /**
     * Get escalation logs for this request.
     */
    public function escalationLogs()
    {
        return $this->hasMany(ApprovalEscalationLog::class, 'approval_request_id');
    }

    /**
     * Check if request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if request is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if request is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Approve this request.
     */
    public function approve(int $userId, ?string $comments = null): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'responded_by' => $userId,
            'responded_at' => now(),
            'comments' => $comments,
        ]);
    }

    /**
     * Reject this request.
     */
    public function reject(int $userId, ?string $comments = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'responded_by' => $userId,
            'responded_at' => now(),
            'comments' => $comments,
        ]);
    }

    /**
     * Mark this request as expired.
     */
    public function markExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
            'responded_at' => now(),
        ]);
    }

    /**
     * Get available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_EXPIRED => 'Expired',
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
     * Scope to find pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to find requests that need a reminder.
     */
    public function scopeNeedsReminder($query, int $reminderHours, int $maxReminders)
    {
        return $query->pending()
            ->where('reminder_count', '<', $maxReminders)
            ->where(function ($q) use ($reminderHours) {
                $q->whereNull('last_reminder_at')
                    ->where('created_at', '<=', now()->subHours($reminderHours));
            })
            ->orWhere(function ($q) use ($reminderHours) {
                $q->whereNotNull('last_reminder_at')
                    ->where('last_reminder_at', '<=', now()->subHours($reminderHours));
            });
    }

    /**
     * Scope to find requests that need escalation.
     */
    public function scopeNeedsEscalation($query, int $escalationHours)
    {
        return $query->pending()
            ->whereNull('escalated_at')
            ->where('created_at', '<=', now()->subHours($escalationHours));
    }

    /**
     * Scope to find requests that should auto-reject.
     */
    public function scopeNeedsAutoReject($query, int $autoRejectDays)
    {
        return $query->pending()
            ->where('created_at', '<=', now()->subDays($autoRejectDays));
    }

    /**
     * Record a reminder sent.
     */
    public function recordReminder(): void
    {
        $this->update([
            'reminder_count' => $this->reminder_count + 1,
            'last_reminder_at' => now(),
        ]);
    }

    /**
     * Escalate this request to another user.
     */
    public function escalateTo(int $newApproverId, string $reason): void
    {
        $originalApproverId = $this->original_approver_id ?? $this->requested_by;

        $this->update([
            'original_approver_id' => $originalApproverId,
            'escalated_at' => now(),
            'escalated_from_id' => $this->responded_by ?? $originalApproverId,
        ]);

        ApprovalEscalationLog::logEscalation(
            $this,
            $this->escalated_from_id,
            $newApproverId,
            $reason
        );
    }

    /**
     * Reassign this request to another user.
     */
    public function reassignTo(int $newApproverId, int $reassignedBy, string $reason): void
    {
        $originalApproverId = $this->original_approver_id ?? $this->requested_by;

        $this->update([
            'original_approver_id' => $originalApproverId,
        ]);

        ApprovalEscalationLog::logReassignment(
            $this,
            $reassignedBy,
            $newApproverId,
            $reason
        );
    }

    /**
     * Check if this request has been escalated.
     */
    public function isEscalated(): bool
    {
        return $this->escalated_at !== null;
    }

    /**
     * Check if this request was via delegation.
     */
    public function isViaDelegation(): bool
    {
        return $this->delegation_id !== null;
    }

    /**
     * Get hours since request was created.
     */
    public function getHoursPending(): float
    {
        return $this->created_at->diffInHours(now(), true);
    }
}
