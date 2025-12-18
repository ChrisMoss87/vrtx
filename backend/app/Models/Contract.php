<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contract_number',
        'related_module',
        'related_id',
        'type',
        'status',
        'value',
        'currency',
        'billing_frequency',
        'start_date',
        'end_date',
        'renewal_date',
        'renewal_notice_days',
        'auto_renew',
        'renewal_status',
        'owner_id',
        'terms',
        'notes',
        'custom_fields',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'renewal_date' => 'date',
        'auto_renew' => 'boolean',
        'custom_fields' => 'array',
    ];

    public function lineItems(): HasMany
    {
        return $this->hasMany(ContractLineItem::class)->orderBy('display_order');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Renewal::class);
    }

    public function currentRenewal(): HasMany
    {
        return $this->hasMany(Renewal::class)->whereIn('status', ['pending', 'in_progress'])->latest();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(RenewalReminder::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        return now()->diffInDays($this->end_date, false);
    }

    public function getIsExpiringAttribute(): bool
    {
        return $this->days_until_expiry <= $this->renewal_notice_days && $this->days_until_expiry >= 0;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->days_until_expiry < 0;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiring($query, $withinDays = 30)
    {
        return $query->where('status', 'active')
            ->where('end_date', '>=', now())
            ->where('end_date', '<=', now()->addDays($withinDays));
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
            ->where('end_date', '<', now());
    }

    public function scopeForModule($query, string $module, int $recordId)
    {
        return $query->where('related_module', $module)->where('related_id', $recordId);
    }

    public static function generateContractNumber(): string
    {
        $prefix = 'CON';
        $year = now()->format('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('%s-%s-%05d', $prefix, $year, $count);
    }
}
