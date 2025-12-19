<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Tenant subscription model (per-tenant database)
 */
class TenantSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan',
        'status',
        'billing_cycle',
        'user_count',
        'price_per_user',
        'external_subscription_id',
        'external_customer_id',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
    ];

    protected $casts = [
        'price_per_user' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Plans
    public const PLAN_FREE = 'free';
    public const PLAN_STARTER = 'starter';
    public const PLAN_PROFESSIONAL = 'professional';
    public const PLAN_BUSINESS = 'business';
    public const PLAN_ENTERPRISE = 'enterprise';

    // Statuses
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_TRIALING = 'trialing';

    // Billing cycles
    public const CYCLE_MONTHLY = 'monthly';
    public const CYCLE_YEARLY = 'yearly';

    // Plan hierarchy for comparison
    public const PLAN_HIERARCHY = [
        self::PLAN_FREE => 0,
        self::PLAN_STARTER => 1,
        self::PLAN_PROFESSIONAL => 2,
        self::PLAN_BUSINESS => 3,
        self::PLAN_ENTERPRISE => 4,
    ];

    // Plan pricing
    public const PLAN_PRICING = [
        self::PLAN_FREE => 0,
        self::PLAN_STARTER => 15,
        self::PLAN_PROFESSIONAL => 45,
        self::PLAN_BUSINESS => 85,
        self::PLAN_ENTERPRISE => 150,
    ];

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isTrialing(): bool
    {
        return $this->status === self::STATUS_TRIALING;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function isYearly(): bool
    {
        return $this->billing_cycle === self::CYCLE_YEARLY;
    }

    /**
     * Check if current plan meets or exceeds required plan
     */
    public function hasPlan(string $requiredPlan): bool
    {
        $currentLevel = self::PLAN_HIERARCHY[$this->plan] ?? 0;
        $requiredLevel = self::PLAN_HIERARCHY[$requiredPlan] ?? 0;

        return $currentLevel >= $requiredLevel;
    }

    /**
     * Get monthly cost
     */
    public function getMonthlyTotalAttribute(): float
    {
        return $this->price_per_user * $this->user_count;
    }

    /**
     * Get days remaining in trial
     */
    public function getTrialDaysRemainingAttribute(): ?int
    {
        if (!$this->trial_ends_at) {
            return null;
        }

        return max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    /**
     * Check if trial has expired
     */
    public function isTrialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }
}
