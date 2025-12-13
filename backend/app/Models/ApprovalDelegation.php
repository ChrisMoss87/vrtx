<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDelegation extends Model
{
    public const TYPE_ALL = 'all';
    public const TYPE_SPECIFIC_RULES = 'specific_rules';

    protected $fillable = [
        'delegator_id',
        'delegate_id',
        'delegation_type',
        'rule_ids',
        'start_date',
        'end_date',
        'reason',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'rule_ids' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'delegation_type' => self::TYPE_ALL,
        'is_active' => true,
    ];

    // Relationships
    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    public function scopeForDelegator($query, int $userId)
    {
        return $query->where('delegator_id', $userId);
    }

    public function scopeForDelegate($query, int $userId)
    {
        return $query->where('delegate_id', $userId);
    }

    // Helpers
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->start_date > $today) {
            return false;
        }

        if ($this->end_date && $this->end_date < $today) {
            return false;
        }

        return true;
    }

    public function appliesToRule(int $ruleId): bool
    {
        if ($this->delegation_type === self::TYPE_ALL) {
            return true;
        }

        return in_array($ruleId, $this->rule_ids ?? []);
    }

    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    public static function findActiveDelegate(int $delegatorId, ?int $ruleId = null): ?int
    {
        $query = static::active()->forDelegator($delegatorId);

        $delegation = $query->first();

        if (!$delegation) {
            return null;
        }

        if ($ruleId && !$delegation->appliesToRule($ruleId)) {
            return null;
        }

        return $delegation->delegate_id;
    }
}
