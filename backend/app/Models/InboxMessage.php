<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'direction',
        'type',
        'from_email',
        'from_name',
        'to_emails',
        'cc_emails',
        'bcc_emails',
        'subject',
        'body_text',
        'body_html',
        'attachments',
        'status',
        'sent_by',
        'external_message_id',
        'in_reply_to',
        'raw_headers',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'to_emails' => 'array',
        'cc_emails' => 'array',
        'bcc_emails' => 'array',
        'attachments' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(InboxConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeReplies($query)
    {
        return $query->where('type', 'reply');
    }

    public function scopeNotes($query)
    {
        return $query->where('type', 'note');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function isNote(): bool
    {
        return $this->type === 'note';
    }

    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    public function getPlainBody(): string
    {
        if ($this->body_text) {
            return $this->body_text;
        }

        // Strip HTML if only HTML body exists
        if ($this->body_html) {
            return strip_tags($this->body_html);
        }

        return '';
    }

    public function getSnippet(int $length = 100): string
    {
        $text = $this->getPlainBody();
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function markAsDelivered(): void
    {
        if (!$this->delivered_at) {
            $this->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);
        }
    }
}
