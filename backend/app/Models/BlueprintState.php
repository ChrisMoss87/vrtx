<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BlueprintState extends Model
{
    use HasFactory;

    protected $fillable = [
        'blueprint_id',
        'name',
        'field_option_value',
        'color',
        'is_initial',
        'is_terminal',
        'position_x',
        'position_y',
        'metadata',
    ];

    protected $casts = [
        'blueprint_id' => 'integer',
        'is_initial' => 'boolean',
        'is_terminal' => 'boolean',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'is_initial' => false,
        'is_terminal' => false,
    ];

    /**
     * Get the blueprint this state belongs to.
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Get transitions that start from this state.
     */
    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(BlueprintTransition::class, 'from_state_id')->orderBy('display_order');
    }

    /**
     * Get transitions that end at this state.
     */
    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(BlueprintTransition::class, 'to_state_id')->orderBy('display_order');
    }

    /**
     * Get the SLA for this state.
     */
    public function sla(): HasOne
    {
        return $this->hasOne(BlueprintSla::class, 'state_id');
    }

    /**
     * Get records currently in this state.
     */
    public function recordStates(): HasMany
    {
        return $this->hasMany(BlueprintRecordState::class, 'current_state_id');
    }

    /**
     * Scope to find initial states.
     */
    public function scopeInitial($query)
    {
        return $query->where('is_initial', true);
    }

    /**
     * Scope to find terminal states.
     */
    public function scopeTerminal($query)
    {
        return $query->where('is_terminal', true);
    }
}
