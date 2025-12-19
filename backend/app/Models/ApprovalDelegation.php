<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ApprovalDelegation extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegator_id',
        'delegate_id',
        'start_date',
        'end_date',
        'reason',
        'is_active',
        'notify_delegator',
        'scope',
    ];

    protected $casts = [
        'delegator_id' => 'integer',
        'delegate_id' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'notify_delegator' => 'boolean',
        'scope' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
        'notify_delegator' => true,
    ];

    /**
     * Get the user who delegated their approval authority.
     */
    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    /**
     * Get the user who received the delegation.
     */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    /**
     * Scope to find active delegations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Scope to find delegations for a specific delegator.
     */
    public function scopeForDelegator(Builder $query, int $userId): Builder
    {
        return $query->where('delegator_id', $userId);
    }

    /**
     * Scope to find delegations for a specific delegate.
     */
    public function scopeForDelegate(Builder $query, int $userId): Builder
    {
        return $query->where('delegate_id', $userId);
    }

    /**
     * Check if this delegation is currently active.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->start_date > $now) {
            return false;
        }

        if ($this->end_date !== null && $this->end_date < $now) {
            return false;
        }

        return true;
    }

    /**
     * Check if this delegation applies to a specific blueprint.
     */
    public function appliesToBlueprint(?int $blueprintId): bool
    {
        // If no scope is set, applies to all
        if (empty($this->scope)) {
            return true;
        }

        // Check if specific blueprint IDs are in scope
        $blueprintIds = $this->scope['blueprint_ids'] ?? [];
        if (!empty($blueprintIds) && $blueprintId !== null) {
            return in_array($blueprintId, $blueprintIds);
        }

        // Check if specific module IDs are in scope
        $moduleIds = $this->scope['module_ids'] ?? [];
        if (!empty($moduleIds) && $blueprintId !== null) {
            // Would need to look up the blueprint's module
            // For now, return true if no blueprint IDs are specified
            return empty($blueprintIds);
        }

        return true;
    }

    /**
     * Find an active delegation for a user.
     */
    public static function findActiveDelegationFor(int $userId, ?int $blueprintId = null): ?self
    {
        $delegations = self::active()
            ->forDelegator($userId)
            ->get();

        foreach ($delegations as $delegation) {
            if ($delegation->appliesToBlueprint($blueprintId)) {
                return $delegation;
            }
        }

        return null;
    }

    /**
     * Find the effective approver (delegate if delegation exists, original otherwise).
     */
    public static function findEffectiveApprover(int $userId, ?int $blueprintId = null): int
    {
        $delegation = self::findActiveDelegationFor($userId, $blueprintId);

        return $delegation ? $delegation->delegate_id : $userId;
    }

    /**
     * Deactivate this delegation.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Extend the delegation end date.
     */
    public function extend(\DateTimeInterface $newEndDate): void
    {
        $this->update(['end_date' => $newEndDate]);
    }
}
