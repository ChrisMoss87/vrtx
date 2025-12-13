<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    protected $fillable = [
        'ticket_id',
        'content',
        'user_id',
        'portal_user_id',
        'is_internal',
        'is_system',
        'attachments',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_system' => 'boolean',
        'attachments' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function getAuthorName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        if ($this->portalUser) {
            return $this->portalUser->name;
        }
        if ($this->is_system) {
            return 'System';
        }
        return 'Unknown';
    }

    public function isFromAgent(): bool
    {
        return $this->user_id !== null;
    }

    public function isFromCustomer(): bool
    {
        return $this->portal_user_id !== null;
    }
}
