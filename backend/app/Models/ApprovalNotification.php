<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalNotification extends Model
{
    public const TYPE_PENDING = 'pending';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_ESCALATION = 'escalation';
    public const TYPE_COMPLETED = 'completed';

    public const TYPES = [
        self::TYPE_PENDING,
        self::TYPE_REMINDER,
        self::TYPE_ESCALATION,
        self::TYPE_COMPLETED,
    ];

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_PUSH = 'push';

    public const CHANNELS = [
        self::CHANNEL_EMAIL,
        self::CHANNEL_IN_APP,
        self::CHANNEL_PUSH,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'request_id',
        'step_id',
        'user_id',
        'notification_type',
        'channel',
        'status',
        'scheduled_at',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected $attributes = [
        'channel' => self::CHANNEL_EMAIL,
        'status' => self::STATUS_PENDING,
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
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeDue($query)
    {
        return $query->where('scheduled_at', '<=', now());
    }

    public function scopeReadyToSend($query)
    {
        return $query->pending()->due();
    }

    // Helpers
    public function markAsSent(): void
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = now();
        $this->save();
    }

    public function markAsFailed(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->save();
    }
}
