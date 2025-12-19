<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blueprint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'module_id',
        'field_id',
        'description',
        'is_active',
        'layout_data',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'field_id' => 'integer',
        'is_active' => 'boolean',
        'layout_data' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the module this blueprint belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the field this blueprint controls.
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    /**
     * Get all states for this blueprint.
     */
    public function states(): HasMany
    {
        return $this->hasMany(BlueprintState::class);
    }

    /**
     * Get all transitions for this blueprint.
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(BlueprintTransition::class)->orderBy('display_order');
    }

    /**
     * Get all SLAs for this blueprint.
     */
    public function slas(): HasMany
    {
        return $this->hasMany(BlueprintSla::class);
    }

    /**
     * Get all record states for this blueprint.
     */
    public function recordStates(): HasMany
    {
        return $this->hasMany(BlueprintRecordState::class);
    }

    /**
     * Get the initial state for this blueprint.
     */
    public function getInitialState(): ?BlueprintState
    {
        return $this->states()->where('is_initial', true)->first();
    }

    /**
     * Get terminal states for this blueprint.
     */
    public function getTerminalStates()
    {
        return $this->states()->where('is_terminal', true)->get();
    }

    /**
     * Get the current state for a record.
     */
    public function getRecordState(int $recordId): ?BlueprintRecordState
    {
        return $this->recordStates()->where('record_id', $recordId)->first();
    }

    /**
     * Scope to only active blueprints.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by module.
     */
    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Scope to filter by field.
     */
    public function scopeForField($query, int $fieldId)
    {
        return $query->where('field_id', $fieldId);
    }
}
