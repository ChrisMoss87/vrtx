<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Plugin bundle model (central database - not per-tenant)
 */
class PluginBundle extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'plugins',
        'price_monthly',
        'price_yearly',
        'discount_percent',
        'icon',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'plugins' => 'array',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the Plugin models for this bundle
     */
    public function getPluginModels()
    {
        return Plugin::whereIn('slug', $this->plugins ?? [])->get();
    }

    /**
     * Calculate à la carte price for comparison
     */
    public function getAlaCarteMonthlyAttribute(): float
    {
        return Plugin::whereIn('slug', $this->plugins ?? [])
            ->sum('price_monthly');
    }

    /**
     * Get savings percentage
     */
    public function getSavingsPercentAttribute(): float
    {
        $alaCarte = $this->ala_carte_monthly;
        if ($alaCarte <= 0) {
            return 0;
        }

        return round((($alaCarte - $this->price_monthly) / $alaCarte) * 100);
    }
}
