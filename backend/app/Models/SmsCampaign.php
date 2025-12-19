<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'connection_id',
        'template_id',
        'message_content',
        'status',
        'target_module',
        'target_filters',
        'phone_field',
        'scheduled_at',
        'started_at',
        'completed_at',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'failed_count',
        'opted_out_count',
        'reply_count',
        'created_by',
    ];

    protected $casts = [
        'target_filters' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SmsConnection::class, 'connection_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SmsTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class, 'campaign_id');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['scheduled', 'sending']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'sent');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isSending(): bool
    {
        return $this->status === 'sending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'sent';
    }

    public function canEdit(): bool
    {
        return in_array($this->status, ['draft', 'scheduled']);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['scheduled', 'sending']);
    }

    public function getMessageContent(): string
    {
        if ($this->template) {
            return $this->template->content;
        }
        return $this->message_content ?? '';
    }

    public function getDeliveryRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        return round(($this->delivered_count / $this->sent_count) * 100, 2);
    }

    public function getFailureRate(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        return round(($this->failed_count / $this->sent_count) * 100, 2);
    }

    public function getProgress(): float
    {
        if ($this->total_recipients === 0) {
            return 0;
        }
        return round(($this->sent_count / $this->total_recipients) * 100, 2);
    }

    public function incrementSent(): void
    {
        $this->increment('sent_count');
    }

    public function incrementDelivered(): void
    {
        $this->increment('delivered_count');
    }

    public function incrementFailed(): void
    {
        $this->increment('failed_count');
    }

    public function incrementOptedOut(): void
    {
        $this->increment('opted_out_count');
    }

    public function incrementReplies(): void
    {
        $this->increment('reply_count');
    }

    public function start(): void
    {
        $this->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'sent',
            'completed_at' => now(),
        ]);
    }

    public function pause(): void
    {
        $this->update(['status' => 'paused']);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}
