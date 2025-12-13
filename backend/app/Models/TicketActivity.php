<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends Model
{
    protected $fillable = [
        'ticket_id',
        'action',
        'changes',
        'user_id',
        'portal_user_id',
        'note',
    ];

    protected $casts = [
        'changes' => 'array',
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

    public function getActorName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        if ($this->portalUser) {
            return $this->portalUser->name;
        }
        return 'System';
    }

    public static function getActionLabels(): array
    {
        return [
            'created' => 'Ticket created',
            'assigned' => 'Ticket assigned',
            'reassigned' => 'Ticket reassigned',
            'status_changed' => 'Status changed',
            'priority_changed' => 'Priority changed',
            'replied' => 'Reply added',
            'internal_note' => 'Internal note added',
            'escalated' => 'Ticket escalated',
            'resolved' => 'Ticket resolved',
            'closed' => 'Ticket closed',
            'reopened' => 'Ticket reopened',
            'merged' => 'Ticket merged',
            'tagged' => 'Tags updated',
            'category_changed' => 'Category changed',
        ];
    }
}
