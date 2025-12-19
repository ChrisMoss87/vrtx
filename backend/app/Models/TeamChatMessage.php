<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'channel_id',
        'notification_id',
        'message_id',
        'content',
        'attachments',
        'status',
        'error_code',
        'error_message',
        'module_record_id',
        'module_api_name',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'sent_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TeamChatConnection::class, 'connection_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TeamChatChannel::class, 'channel_id');
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(TeamChatNotification::class, 'notification_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function moduleRecord(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'module_record_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->whereIn('status', ['sent', 'delivered']);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForRecord($query, string $moduleApiName, int $recordId)
    {
        return $query->where('module_api_name', $moduleApiName)
                     ->where('module_record_id', $recordId);
    }

    public function markAsSent(string $messageId): void
    {
        $this->update([
            'status' => 'sent',
            'message_id' => $messageId,
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update(['status' => 'delivered']);
    }

    public function markAsFailed(string $errorCode, string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }
}
