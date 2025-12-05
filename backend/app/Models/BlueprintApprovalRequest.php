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
        'status',
        'responded_by',
        'responded_at',
        'comments',
    ];

    protected $casts = [
        'approval_id' => 'integer',
        'record_id' => 'integer',
        'execution_id' => 'integer',
        'requested_by' => 'integer',
        'responded_by' => 'integer',
        'responded_at' => 'datetime',
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
}
