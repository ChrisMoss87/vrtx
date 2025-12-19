<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalReminder extends Model
{
    use HasFactory;
    protected $fillable = [
        'contract_id',
        'days_before',
        'reminder_type',
        'recipients',
        'template',
        'is_sent',
        'sent_at',
        'scheduled_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function scopePending($query)
    {
        return $query->where('is_sent', false);
    }

    public function scopeDue($query)
    {
        return $query->where('is_sent', false)
            ->where('scheduled_at', '<=', now());
    }
}
