<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealRoomActionItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    public const PARTY_SELLER = 'seller';
    public const PARTY_BUYER = 'buyer';
    public const PARTY_BOTH = 'both';

    protected $fillable = [
        'room_id',
        'title',
        'description',
        'assigned_to',
        'assigned_party',
        'due_date',
        'status',
        'display_order',
        'completed_at',
        'completed_by',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(DealRoom::class, 'room_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(DealRoomMember::class, 'assigned_to');
    }

    public function completedByMember(): BelongsTo
    {
        return $this->belongsTo(DealRoomMember::class, 'completed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markComplete(int $memberId): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $memberId,
        ]);

        return $this;
    }

    public function markPending(): self
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'completed_at' => null,
            'completed_by' => null,
        ]);

        return $this;
    }

    public function isOverdue(): bool
    {
        if (!$this->due_date || $this->status === self::STATUS_COMPLETED) {
            return false;
        }

        return $this->due_date->isPast();
    }

    public function isDueSoon(int $days = 3): bool
    {
        if (!$this->due_date || $this->status === self::STATUS_COMPLETED) {
            return false;
        }

        return $this->due_date->isBetween(now(), now()->addDays($days));
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', self::STATUS_COMPLETED)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }
}
