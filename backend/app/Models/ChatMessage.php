<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'content',
        'content_type',
        'attachments',
        'metadata',
        'is_internal',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'metadata' => 'array',
        'is_internal' => 'boolean',
        'read_at' => 'datetime',
    ];

    public const SENDER_VISITOR = 'visitor';
    public const SENDER_AGENT = 'agent';
    public const SENDER_SYSTEM = 'system';

    public const CONTENT_TEXT = 'text';
    public const CONTENT_HTML = 'html';
    public const CONTENT_IMAGE = 'image';
    public const CONTENT_FILE = 'file';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isFromVisitor(): bool
    {
        return $this->sender_type === self::SENDER_VISITOR;
    }

    public function isFromAgent(): bool
    {
        return $this->sender_type === self::SENDER_AGENT;
    }

    public function isSystem(): bool
    {
        return $this->sender_type === self::SENDER_SYSTEM;
    }

    public function getSenderName(): string
    {
        if ($this->isFromVisitor()) {
            return $this->conversation->visitor->getDisplayName();
        }

        if ($this->isFromAgent() && $this->sender) {
            return $this->sender->name;
        }

        if ($this->isSystem()) {
            return 'System';
        }

        return 'Unknown';
    }
}
