<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalNotification extends Model
{
    protected $fillable = [
        'portal_user_id',
        'type',
        'title',
        'message',
        'action_url',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if (!$this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    public static function getTypes(): array
    {
        return [
            'deal_update' => 'Deal Update',
            'invoice_due' => 'Invoice Due',
            'invoice_paid' => 'Invoice Paid',
            'document_shared' => 'Document Shared',
            'quote_ready' => 'Quote Ready',
            'ticket_reply' => 'Ticket Reply',
            'ticket_closed' => 'Ticket Closed',
            'announcement' => 'Announcement',
        ];
    }
}
