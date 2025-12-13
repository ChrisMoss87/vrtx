<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApprovalRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'uuid',
        'rule_id',
        'entity_type',
        'entity_id',
        'title',
        'description',
        'status',
        'snapshot_data',
        'value',
        'currency',
        'submitted_at',
        'completed_at',
        'expires_at',
        'requested_by',
        'final_approver_id',
        'final_comments',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'value' => 'decimal:2',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ApprovalRequest $request) {
            if (empty($request->uuid)) {
                $request->uuid = Str::uuid()->toString();
            }
        });
    }

    // Relationships
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'rule_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function finalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_approver_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'request_id')->orderBy('step_order');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class, 'request_id')->orderByDesc('created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ApprovalNotification::class, 'request_id');
    }

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)
                     ->where('entity_id', $entityId);
    }

    public function scopePendingForUser($query, int $userId)
    {
        return $query->pending()
            ->whereHas('steps', function ($q) use ($userId) {
                $q->where('approver_id', $userId)
                  ->where('is_current', true)
                  ->where('status', ApprovalStep::STATUS_PENDING);
            });
    }

    // Helpers
    public function submit(): void
    {
        $this->status = self::STATUS_IN_PROGRESS;
        $this->submitted_at = now();
        $this->save();

        // Activate first step
        $this->activateNextStep();

        $this->logHistory('submitted', null, 'Approval request submitted');
    }

    public function approve(int $userId, ?string $comments = null): void
    {
        $currentStep = $this->getCurrentStep();

        if ($currentStep && $currentStep->approver_id === $userId) {
            $currentStep->approve($comments);
        }

        // Check if all steps are approved
        if ($this->allStepsApproved()) {
            $this->status = self::STATUS_APPROVED;
            $this->completed_at = now();
            $this->final_approver_id = $userId;
            $this->final_comments = $comments;
            $this->save();

            $this->logHistory('approved', $userId, $comments);
        } else {
            $this->activateNextStep();
        }
    }

    public function reject(int $userId, ?string $comments = null): void
    {
        $currentStep = $this->getCurrentStep();

        if ($currentStep && $currentStep->approver_id === $userId) {
            $currentStep->reject($comments);
        }

        $this->status = self::STATUS_REJECTED;
        $this->completed_at = now();
        $this->final_approver_id = $userId;
        $this->final_comments = $comments;
        $this->save();

        $this->logHistory('rejected', $userId, $comments);
    }

    public function cancel(?string $reason = null): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->completed_at = now();
        $this->save();

        $this->logHistory('cancelled', null, $reason);
    }

    public function getCurrentStep(): ?ApprovalStep
    {
        return $this->steps()->where('is_current', true)->first();
    }

    public function activateNextStep(): void
    {
        $nextStep = $this->steps()
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->orderBy('step_order')
            ->first();

        if ($nextStep) {
            // Deactivate current step
            $this->steps()->where('is_current', true)->update(['is_current' => false]);

            // Activate next step
            $nextStep->is_current = true;
            $nextStep->notified_at = now();
            $nextStep->due_at = $this->rule?->sla_hours
                ? now()->addHours($this->rule->sla_hours)
                : null;
            $nextStep->save();
        }
    }

    public function allStepsApproved(): bool
    {
        return $this->steps()
            ->where('status', '!=', ApprovalStep::STATUS_APPROVED)
            ->where('status', '!=', ApprovalStep::STATUS_SKIPPED)
            ->doesntExist();
    }

    public function logHistory(string $action, ?int $userId = null, ?string $comments = null, array $changes = []): ApprovalHistory
    {
        return $this->history()->create([
            'step_id' => $this->getCurrentStep()?->id,
            'user_id' => $userId,
            'action' => $action,
            'comments' => $comments,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function createStepsFromRule(): void
    {
        if (!$this->rule) {
            return;
        }

        $approvers = $this->rule->getApprovers();

        foreach ($approvers as $index => $approver) {
            $this->steps()->create([
                'approver_id' => $approver['user_id'] ?? null,
                'role_id' => $approver['role_id'] ?? null,
                'approver_type' => $approver['type'] ?? 'user',
                'step_order' => $index + 1,
            ]);
        }
    }
}
