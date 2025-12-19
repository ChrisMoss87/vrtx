<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BlueprintTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'blueprint_id',
        'from_state_id',
        'to_state_id',
        'name',
        'description',
        'button_label',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'blueprint_id' => 'integer',
        'from_state_id' => 'integer',
        'to_state_id' => 'integer',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'display_order' => 0,
        'is_active' => true,
    ];

    /**
     * Get the blueprint this transition belongs to.
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Get the source state (null for initial transitions).
     */
    public function fromState(): BelongsTo
    {
        return $this->belongsTo(BlueprintState::class, 'from_state_id');
    }

    /**
     * Get the destination state.
     */
    public function toState(): BelongsTo
    {
        return $this->belongsTo(BlueprintState::class, 'to_state_id');
    }

    /**
     * Get conditions for this transition (before-phase).
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(BlueprintTransitionCondition::class, 'transition_id')->orderBy('display_order');
    }

    /**
     * Get requirements for this transition (during-phase).
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(BlueprintTransitionRequirement::class, 'transition_id')->orderBy('display_order');
    }

    /**
     * Get actions for this transition (after-phase).
     */
    public function actions(): HasMany
    {
        return $this->hasMany(BlueprintTransitionAction::class, 'transition_id')->orderBy('display_order');
    }

    /**
     * Get approval configuration for this transition.
     */
    public function approval(): HasOne
    {
        return $this->hasOne(BlueprintApproval::class, 'transition_id');
    }

    /**
     * Get executions of this transition.
     */
    public function executions(): HasMany
    {
        return $this->hasMany(BlueprintTransitionExecution::class, 'transition_id');
    }

    /**
     * Check if this transition requires approval.
     */
    public function requiresApproval(): bool
    {
        return $this->approval()->exists();
    }

    /**
     * Check if this transition has any requirements.
     */
    public function hasRequirements(): bool
    {
        return $this->requirements()->exists();
    }

    /**
     * Get the button label (or name if not set).
     */
    public function getButtonLabel(): string
    {
        return $this->button_label ?? $this->name;
    }

    /**
     * Scope to only active transitions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to find transitions from a specific state.
     */
    public function scopeFromState($query, ?int $stateId)
    {
        if ($stateId === null) {
            return $query->whereNull('from_state_id');
        }
        return $query->where('from_state_id', $stateId);
    }

    /**
     * Scope to find transitions to a specific state.
     */
    public function scopeToState($query, int $stateId)
    {
        return $query->where('to_state_id', $stateId);
    }
}
