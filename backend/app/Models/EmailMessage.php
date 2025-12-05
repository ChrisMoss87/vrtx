<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailMessage extends Model
{
    use HasFactory, SoftDeletes;

    // Status types
    public const STATUS_DRAFT = 'draft';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RECEIVED = 'received';

    // Direction
    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    protected $fillable = [
        'account_id',
        'user_id',
        'message_id',
        'thread_id',
        'parent_id',
        'direction',
        'status',
        'from_email',
        'from_name',
        'to_emails',
        'cc_emails',
        'bcc_emails',
        'reply_to',
        'subject',
        'body_html',
        'body_text',
        'headers',
        'folder',
        'is_read',
        'is_starred',
        'is_important',
        'has_attachments',
        'attachments',
        'tracking_id',
        'opened_at',
        'open_count',
        'clicked_at',
        'click_count',
        'linked_record_type',
        'linked_record_id',
        'template_id',
        'sent_at',
        'received_at',
        'scheduled_at',
        'failed_reason',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'user_id' => 'integer',
        'parent_id' => 'integer',
        'to_emails' => 'array',
        'cc_emails' => 'array',
        'bcc_emails' => 'array',
        'headers' => 'array',
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
        'is_important' => 'boolean',
        'has_attachments' => 'boolean',
        'attachments' => 'array',
        'open_count' => 'integer',
        'click_count' => 'integer',
        'template_id' => 'integer',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    protected $attributes = [
        'direction' => self::DIRECTION_OUTBOUND,
        'status' => self::STATUS_DRAFT,
        'folder' => 'INBOX',
        'is_read' => false,
        'is_starred' => false,
        'is_important' => false,
        'has_attachments' => false,
        'open_count' => 0,
        'click_count' => 0,
        'to_emails' => '[]',
        'cc_emails' => '[]',
        'bcc_emails' => '[]',
        'attachments' => '[]',
    ];

    /**
     * Get the email account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'account_id');
    }

    /**
     * Get the user who sent/received this email.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent email (for replies).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'parent_id');
    }

    /**
     * Get replies to this email.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'parent_id');
    }

    /**
     * Get all emails in the same thread.
     */
    public function thread(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'thread_id', 'thread_id')
            ->orderBy('received_at');
    }

    /**
     * Get the template used.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    /**
     * Get the linked record (polymorphic).
     */
    public function linkedRecord(): MorphTo
    {
        return $this->morphTo('linked_record', 'linked_record_type', 'linked_record_id');
    }

    /**
     * Scope for inbox emails.
     */
    public function scopeInbox($query)
    {
        return $query->where('direction', self::DIRECTION_INBOUND)
            ->where('folder', 'INBOX');
    }

    /**
     * Scope for sent emails.
     */
    public function scopeSent($query)
    {
        return $query->where('direction', self::DIRECTION_OUTBOUND)
            ->where('status', self::STATUS_SENT);
    }

    /**
     * Scope for draft emails.
     */
    public function scopeDrafts($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope for unread emails.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for starred emails.
     */
    public function scopeStarred($query)
    {
        return $query->where('is_starred', true);
    }

    /**
     * Scope for emails linked to a record.
     */
    public function scopeForRecord($query, string $type, int $id)
    {
        return $query->where('linked_record_type', $type)
            ->where('linked_record_id', $id);
    }

    /**
     * Mark as read.
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Mark as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['is_read' => false]);
    }

    /**
     * Toggle starred status.
     */
    public function toggleStar(): void
    {
        $this->update(['is_starred' => !$this->is_starred]);
    }

    /**
     * Record an email open.
     */
    public function recordOpen(): void
    {
        $this->increment('open_count');
        if (!$this->opened_at) {
            $this->update(['opened_at' => now()]);
        }
    }

    /**
     * Record a link click.
     */
    public function recordClick(): void
    {
        $this->increment('click_count');
        if (!$this->clicked_at) {
            $this->update(['clicked_at' => now()]);
        }
    }

    /**
     * Get formatted recipients.
     */
    public function getFormattedToAttribute(): string
    {
        return collect($this->to_emails)->implode(', ');
    }

    /**
     * Get preview of body (first 100 chars).
     */
    public function getPreviewAttribute(): string
    {
        $text = $this->body_text ?: strip_tags($this->body_html ?? '');
        return \Str::limit(trim($text), 100);
    }

    /**
     * Check if email was opened.
     */
    public function wasOpened(): bool
    {
        return $this->open_count > 0;
    }

    /**
     * Check if any link was clicked.
     */
    public function wasClicked(): bool
    {
        return $this->click_count > 0;
    }

    /**
     * Generate a unique thread ID.
     */
    public static function generateThreadId(): string
    {
        return 'thread_' . uniqid() . '_' . time();
    }

    /**
     * Generate a unique tracking ID.
     */
    public static function generateTrackingId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
