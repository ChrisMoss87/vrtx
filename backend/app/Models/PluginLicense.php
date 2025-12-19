<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Plugin license model (per-tenant database)
 */
class PluginLicense extends Model
{
    use HasFactory;

    protected $fillable = [
        'plugin_slug',
        'bundle_slug',
        'status',
        'pricing_model',
        'quantity',
        'price_monthly',
        'external_subscription_item_id',
        'activated_at',
        'expires_at',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Statuses
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForPlugin($query, string $pluginSlug)
    {
        return $query->where('plugin_slug', $pluginSlug);
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFromBundle(): bool
    {
        return !empty($this->bundle_slug);
    }

    /**
     * Get the plugin catalog entry
     */
    public function getPlugin(): ?Plugin
    {
        return Plugin::where('slug', $this->plugin_slug)->first();
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return max(0, now()->diffInDays($this->expires_at, false));
    }
}
