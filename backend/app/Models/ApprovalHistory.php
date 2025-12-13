<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalHistory extends Model
{
    public $timestamps = false;

    public const ACTION_SUBMITTED = 'submitted';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_DELEGATED = 'delegated';
    public const ACTION_ESCALATED = 'escalated';
    public const ACTION_COMMENTED = 'commented';
    public const ACTION_RECALLED = 'recalled';
    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_STEP_APPROVED = 'step_approved';
    public const ACTION_STEP_REJECTED = 'step_rejected';
    public const ACTION_STEP_SKIPPED = 'step_skipped';

    protected $table = 'approval_history';

    protected $fillable = [
        'request_id',
        'step_id',
        'user_id',
        'action',
        'comments',
        'changes',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    // Helpers
    public function getActionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_SUBMITTED => 'Submitted for approval',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_REJECTED => 'Rejected',
            self::ACTION_DELEGATED => 'Delegated',
            self::ACTION_ESCALATED => 'Escalated',
            self::ACTION_COMMENTED => 'Comment added',
            self::ACTION_RECALLED => 'Recalled',
            self::ACTION_CANCELLED => 'Cancelled',
            self::ACTION_STEP_APPROVED => 'Step approved',
            self::ACTION_STEP_REJECTED => 'Step rejected',
            self::ACTION_STEP_SKIPPED => 'Step skipped',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
