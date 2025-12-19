<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalEscalationLog extends Model
{
    use HasFactory;

    // Escalation types
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_ESCALATE = 'escalate';
    public const TYPE_AUTO_REJECT = 'auto_reject';
    public const TYPE_REASSIGN = 'reassign';

    protected $fillable = [
        'approval_request_id',
        'escalation_type',
        'from_user_id',
        'to_user_id',
        'reason',
    ];

    protected $casts = [
        'approval_request_id' => 'integer',
        'from_user_id' => 'integer',
        'to_user_id' => 'integer',
    ];

    /**
     * Get the approval request this log belongs to.
     */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(BlueprintApprovalRequest::class, 'approval_request_id');
    }

    /**
     * Get the user this escalation was from.
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * Get the user this escalation was to.
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Get available escalation types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_REMINDER => 'Reminder Sent',
            self::TYPE_ESCALATE => 'Escalated to Another User',
            self::TYPE_AUTO_REJECT => 'Auto-Rejected',
            self::TYPE_REASSIGN => 'Manually Reassigned',
        ];
    }

    /**
     * Create a reminder log entry.
     */
    public static function logReminder(BlueprintApprovalRequest $request): self
    {
        return self::create([
            'approval_request_id' => $request->id,
            'escalation_type' => self::TYPE_REMINDER,
            'to_user_id' => $request->responded_by ?? $request->original_approver_id,
            'reason' => 'Approval reminder sent',
        ]);
    }

    /**
     * Create an escalation log entry.
     */
    public static function logEscalation(
        BlueprintApprovalRequest $request,
        int $fromUserId,
        int $toUserId,
        string $reason
    ): self {
        return self::create([
            'approval_request_id' => $request->id,
            'escalation_type' => self::TYPE_ESCALATE,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'reason' => $reason,
        ]);
    }

    /**
     * Create an auto-reject log entry.
     */
    public static function logAutoReject(BlueprintApprovalRequest $request, string $reason): self
    {
        return self::create([
            'approval_request_id' => $request->id,
            'escalation_type' => self::TYPE_AUTO_REJECT,
            'reason' => $reason,
        ]);
    }

    /**
     * Create a reassignment log entry.
     */
    public static function logReassignment(
        BlueprintApprovalRequest $request,
        int $fromUserId,
        int $toUserId,
        string $reason
    ): self {
        return self::create([
            'approval_request_id' => $request->id,
            'escalation_type' => self::TYPE_REASSIGN,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'reason' => $reason,
        ]);
    }
}
