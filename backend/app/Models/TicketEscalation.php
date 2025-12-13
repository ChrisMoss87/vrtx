<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEscalation extends Model
{
    protected $fillable = [
        'ticket_id',
        'type',
        'level',
        'escalated_to',
        'reason',
        'escalated_by',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function acknowledge(int $userId): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);
    }

    public static function getTypes(): array
    {
        return [
            'response_sla' => 'Response SLA Breach',
            'resolution_sla' => 'Resolution SLA Breach',
            'manual' => 'Manual Escalation',
        ];
    }

    public static function getLevels(): array
    {
        return [
            'first' => 'First Level',
            'second' => 'Second Level',
            'third' => 'Third Level',
        ];
    }
}
