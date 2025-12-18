<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'subject',
        'description',
        'status',
        'priority',
        'category_id',
        'submitter_id',
        'portal_user_id',
        'contact_id',
        'account_id',
        'assigned_to',
        'team_id',
        'channel',
        'tags',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'sla_response_due_at',
        'sla_resolution_due_at',
        'sla_response_breached',
        'sla_resolution_breached',
        'satisfaction_rating',
        'satisfaction_feedback',
        'custom_fields',
    ];

    protected $casts = [
        'tags' => 'array',
        'custom_fields' => 'array',
        'sla_response_breached' => 'boolean',
        'sla_resolution_breached' => 'boolean',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_response_due_at' => 'datetime',
        'sla_resolution_due_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class, 'portal_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class, 'team_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class, 'ticket_id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(TicketEscalation::class, 'ticket_id');
    }

    // Scopes
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'pending', 'in_progress']);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeOverdueSla(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('sla_response_breached', true)
                ->orWhere('sla_resolution_breached', true);
        });
    }

    // Helper methods
    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'pending', 'in_progress']);
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function getCustomerName(): string
    {
        if ($this->portalUser) {
            return $this->portalUser->name;
        }
        return 'Unknown Customer';
    }

    public static function generateTicketNumber(): string
    {
        $prefix = 'TKT';
        $year = date('Y');
        $lastTicket = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastTicket ? ((int)substr($lastTicket->ticket_number, -6)) + 1 : 1;

        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }

    public static function getStatuses(): array
    {
        return [
            'open' => 'Open',
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];
    }

    public static function getPriorities(): array
    {
        return [
            1 => 'Low',
            2 => 'Medium',
            3 => 'High',
            4 => 'Urgent',
        ];
    }

    public static function getChannels(): array
    {
        return [
            'portal' => 'Customer Portal',
            'email' => 'Email',
            'phone' => 'Phone',
            'chat' => 'Live Chat',
        ];
    }
}
