<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Wizard extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'created_by',
        'name',
        'api_name',
        'description',
        'type',
        'is_active',
        'is_default',
        'settings',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
        'display_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Wizard $wizard) {
            if (empty($wizard->api_name)) {
                $wizard->api_name = Str::snake($wizard->name);
            }
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WizardStep::class)->orderBy('display_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function getStepCountAttribute(): int
    {
        return $this->steps()->count();
    }

    public function getFieldCountAttribute(): int
    {
        return $this->steps->sum(function ($step) {
            return count($step->fields ?? []);
        });
    }

    public function duplicate(): self
    {
        $clone = $this->replicate();
        $clone->name = $this->name . ' (Copy)';
        $clone->api_name = $this->api_name . '_copy_' . time();
        $clone->is_default = false;
        $clone->save();

        foreach ($this->steps as $step) {
            $stepClone = $step->replicate();
            $stepClone->wizard_id = $clone->id;
            $stepClone->save();
        }

        return $clone;
    }
}
