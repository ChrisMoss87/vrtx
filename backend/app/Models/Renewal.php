<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Renewal extends Model
{
    protected $fillable = [
        'contract_id',
        'status',
        'original_value',
        'renewal_value',
        'upsell_value',
        'renewal_type',
        'due_date',
        'closed_date',
        'owner_id',
        'new_contract_id',
        'loss_reason',
        'notes',
    ];

    protected $casts = [
        'original_value' => 'decimal:2',
        'renewal_value' => 'decimal:2',
        'upsell_value' => 'decimal:2',
        'due_date' => 'date',
        'closed_date' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function newContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'new_contract_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(RenewalActivity::class)->orderBy('created_at', 'desc');
    }

    public function getTotalValueAttribute(): float
    {
        return ($this->renewal_value ?? $this->original_value) + ($this->upsell_value ?? 0);
    }

    public function getGrowthPercentAttribute(): ?float
    {
        if (!$this->renewal_value || $this->original_value <= 0) {
            return null;
        }
        return (($this->renewal_value - $this->original_value) / $this->original_value) * 100;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeWon($query)
    {
        return $query->where('status', 'won');
    }

    public function scopeLost($query)
    {
        return $query->where('status', 'lost');
    }

    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->whereIn('status', ['pending', 'in_progress'])
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays($days));
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress'])
            ->where('due_date', '<', now());
    }
}
