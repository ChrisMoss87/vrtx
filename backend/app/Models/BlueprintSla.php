<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlueprintSla extends Model
{
    use HasFactory;

    protected $table = 'blueprint_slas';

    protected $fillable = [
        'blueprint_id',
        'state_id',
        'name',
        'duration_hours',
        'business_hours_only',
        'exclude_weekends',
        'is_active',
    ];

    protected $casts = [
        'blueprint_id' => 'integer',
        'state_id' => 'integer',
        'duration_hours' => 'integer',
        'business_hours_only' => 'boolean',
        'exclude_weekends' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'business_hours_only' => false,
        'exclude_weekends' => false,
        'is_active' => true,
    ];

    /**
     * Get the blueprint this SLA belongs to.
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Get the state this SLA applies to.
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(BlueprintState::class, 'state_id');
    }

    /**
     * Get escalations for this SLA.
     */
    public function escalations(): HasMany
    {
        return $this->hasMany(BlueprintSlaEscalation::class, 'sla_id')->orderBy('display_order');
    }

    /**
     * Get active instances of this SLA.
     */
    public function instances(): HasMany
    {
        return $this->hasMany(BlueprintSlaInstance::class, 'sla_id');
    }

    /**
     * Scope to only active SLAs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
