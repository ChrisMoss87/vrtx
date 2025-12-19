<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'connection_id',
        'wa_message_id',
        'direction',
        'type',
        'content',
        'media',
        'template_id',
        'template_params',
        'status',
        'error_code',
        'error_message',
        'sent_by',
        'context_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'media' => 'array',
        'template_params' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'template_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function contextMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'context_message_id', 'wa_message_id');
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function hasMedia(): bool
    {
        return !empty($this->media);
    }

    public function isTemplate(): bool
    {
        return $this->type === 'template' && $this->template_id !== null;
    }

    public function getMediaUrlAttribute(): ?string
    {
        return $this->media['url'] ?? null;
    }

    public function getMediaMimeTypeAttribute(): ?string
    {
        return $this->media['mime_type'] ?? null;
    }

    public function getMediaFilenameAttribute(): ?string
    {
        return $this->media['filename'] ?? null;
    }

    public function markAsSent(string $waMessageId): void
    {
        $this->update([
            'wa_message_id' => $waMessageId,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
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
