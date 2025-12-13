<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Playbook extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'trigger_module',
        'trigger_condition',
        'trigger_config',
        'estimated_days',
        'is_active',
        'auto_assign',
        'default_owner_id',
        'tags',
        'display_order',
        'created_by',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'auto_assign' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($playbook) {
            if (empty($playbook->slug)) {
                $playbook->slug = Str::slug($playbook->name);
                $baseSlug = $playbook->slug;
                $counter = 1;
                while (static::where('slug', $playbook->slug)->exists()) {
                    $playbook->slug = $baseSlug . '-' . $counter++;
                }
            }
        });
    }

    public function phases(): HasMany
    {
        return $this->hasMany(PlaybookPhase::class)->orderBy('display_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PlaybookTask::class)->orderBy('display_order');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(PlaybookInstance::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(PlaybookGoal::class);
    }

    public function defaultOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModule($query, string $module)
    {
        return $query->where('trigger_module', $module);
    }

    public function getTaskCount(): int
    {
        return $this->tasks()->count();
    }

    public function getActiveInstanceCount(): int
    {
        return $this->instances()->where('status', 'active')->count();
    }

    public function getCompletedInstanceCount(): int
    {
        return $this->instances()->where('status', 'completed')->count();
    }

    public function getAverageCompletionDays(): ?float
    {
        $completed = $this->instances()
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->get();

        if ($completed->isEmpty()) {
            return null;
        }

        $totalDays = $completed->sum(function ($instance) {
            return $instance->started_at->diffInDays($instance->completed_at);
        });

        return round($totalDays / $completed->count(), 1);
    }
}
