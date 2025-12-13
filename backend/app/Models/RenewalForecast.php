<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenewalForecast extends Model
{
    protected $fillable = [
        'period_start',
        'period_end',
        'period_type',
        'expected_renewals',
        'at_risk_value',
        'churned_value',
        'renewed_value',
        'expansion_value',
        'total_contracts',
        'at_risk_count',
        'renewed_count',
        'churned_count',
        'retention_rate',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'expected_renewals' => 'decimal:2',
        'at_risk_value' => 'decimal:2',
        'churned_value' => 'decimal:2',
        'renewed_value' => 'decimal:2',
        'expansion_value' => 'decimal:2',
        'retention_rate' => 'decimal:2',
    ];

    public function getNetRetentionAttribute(): ?float
    {
        if ($this->expected_renewals <= 0) {
            return null;
        }
        return (($this->renewed_value + $this->expansion_value) / $this->expected_renewals) * 100;
    }

    public function scopeForPeriod($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    public function scopeCurrent($query)
    {
        return $query->where('period_start', '<=', now())
            ->where('period_end', '>=', now());
    }
}
