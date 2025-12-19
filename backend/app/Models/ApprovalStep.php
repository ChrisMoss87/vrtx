<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_DELEGATED = 'delegated';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_SKIPPED,
        self::STATUS_DELEGATED,
    ];

    public const TYPE_USER = 'user';
    public const TYPE_ROLE = 'role';
    public const TYPE_MANAGER = 'manager';
    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'request_id',
        'approver_id',
        'role_id',
        'approver_type',
        'step_order',
        'status',
        'comments',
        'notified_at',
        'viewed_at',
        'decided_at',
        'due_at',
        'is_current',
        'delegated_to_id',
        'delegated_by_id',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'notified_at' => 'datetime',
        'viewed_at' => 'datetime',
        'decided_at' => 'datetime',
        'due_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'approver_type' => self::TYPE_USER,
        'step_order' => 1,
        'is_current' => false,
    ];

    // Relationships
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function delegatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to_id');
    }

    public function delegatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeForApprover($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('approver_id', $userId)
              ->orWhere('delegated_to_id', $userId);
        });
    }

    // Helpers
    public function approve(?string $comments = null): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->comments = $comments;
        $this->decided_at = now();
        $this->is_current = false;
        $this->save();

        $this->request->logHistory('step_approved', $this->approver_id, $comments);
    }

    public function reject(?string $comments = null): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->comments = $comments;
        $this->decided_at = now();
        $this->is_current = false;
        $this->save();

        $this->request->logHistory('step_rejected', $this->approver_id, $comments);
    }

    public function skip(?string $reason = null): void
    {
        $this->status = self::STATUS_SKIPPED;
        $this->comments = $reason;
        $this->decided_at = now();
        $this->is_current = false;
        $this->save();

        $this->request->logHistory('step_skipped', null, $reason);
    }

    public function delegate(int $delegateId, int $delegatorId): void
    {
        $this->delegated_to_id = $delegateId;
        $this->delegated_by_id = $delegatorId;
        $this->status = self::STATUS_DELEGATED;
        $this->save();

        $this->request->logHistory('delegated', $delegatorId, "Delegated to user #{$delegateId}");
    }

    public function markAsViewed(): void
    {
        if (!$this->viewed_at) {
            $this->viewed_at = now();
            $this->save();
        }
    }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && $this->status === self::STATUS_PENDING;
    }

    public function getEffectiveApprover(): ?User
    {
        if ($this->delegated_to_id) {
            return $this->delegatedTo;
        }

        return $this->approver;
    }
}
