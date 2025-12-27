<?php

namespace App\Services;

class TenantSubscription
{
    // Plan constants
    public const PLAN_FREE = 'free';
    public const PLAN_STARTER = 'starter';
    public const PLAN_PROFESSIONAL = 'professional';
    public const PLAN_BUSINESS = 'business';
    public const PLAN_ENTERPRISE = 'enterprise';

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED = 'expired';

    // Billing cycle constants
    public const CYCLE_MONTHLY = 'monthly';
    public const CYCLE_YEARLY = 'yearly';

    // Plan hierarchy (higher number = higher tier)
    public const PLAN_HIERARCHY = [
        self::PLAN_FREE => 0,
        self::PLAN_STARTER => 1,
        self::PLAN_PROFESSIONAL => 2,
        self::PLAN_BUSINESS => 3,
        self::PLAN_ENTERPRISE => 4,
    ];

    // Plan pricing (per user, per month)
    public const PLAN_PRICING = [
        self::PLAN_FREE => 0,
        self::PLAN_STARTER => 15,
        self::PLAN_PROFESSIONAL => 45,
        self::PLAN_BUSINESS => 85,
        self::PLAN_ENTERPRISE => 150,
    ];

    public ?string $plan = null;
    public ?string $status = null;
    public ?string $billing_cycle = null;
    public ?int $user_count = null;
    public ?\DateTimeInterface $trial_ends_at = null;
    public ?\DateTimeInterface $current_period_end = null;
}
