<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSendLog extends Model
{
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_OPENED = 'opened';
    public const STATUS_BOUNCED = 'bounced';

    protected $fillable = [
        'document_id',
        'recipient_email',
        'recipient_name',
        'subject',
        'message',
        'status',
        'sent_at',
        'delivered_at',
        'opened_at',
        'sent_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_SENT,
    ];

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'document_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    // Helpers
    public function markAsDelivered(): void
    {
        $this->status = self::STATUS_DELIVERED;
        $this->delivered_at = now();
        $this->save();
    }

    public function markAsOpened(): void
    {
        $this->status = self::STATUS_OPENED;
        $this->opened_at = now();
        $this->save();
    }

    public function markAsBounced(): void
    {
        $this->status = self::STATUS_BOUNCED;
        $this->save();
    }
}
