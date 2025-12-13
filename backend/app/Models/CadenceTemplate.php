<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CadenceTemplate extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'name',
        'description',
        'category',
        'steps_config',
        'settings',
        'is_system',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'steps_config' => 'array',
        'settings' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Create a cadence from this template
     */
    public function createCadence(int $moduleId, string $name, ?int $ownerId = null): Cadence
    {
        $cadence = Cadence::create([
            'name' => $name,
            'description' => $this->description,
            'module_id' => $moduleId,
            'status' => Cadence::STATUS_DRAFT,
            'settings' => $this->settings,
            'owner_id' => $ownerId,
            'created_by' => auth()->id(),
        ]);

        // Create steps from template
        foreach ($this->steps_config as $index => $stepConfig) {
            $cadence->steps()->create([
                'step_order' => $index + 1,
                'name' => $stepConfig['name'] ?? null,
                'channel' => $stepConfig['channel'] ?? 'email',
                'delay_type' => $stepConfig['delay_type'] ?? 'days',
                'delay_value' => $stepConfig['delay_value'] ?? 1,
                'subject' => $stepConfig['subject'] ?? null,
                'content' => $stepConfig['content'] ?? null,
                'conditions' => $stepConfig['conditions'] ?? null,
                'is_active' => true,
            ]);
        }

        return $cadence;
    }
}
