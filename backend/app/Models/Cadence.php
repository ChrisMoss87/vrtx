<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cadence extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_PAUSED => 'Paused',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    protected $fillable = [
        'name',
        'description',
        'module_id',
        'status',
        'entry_criteria',
        'exit_criteria',
        'settings',
        'auto_enroll',
        'allow_re_enrollment',
        're_enrollment_days',
        'max_enrollments_per_day',
        'created_by',
        'owner_id',
    ];

    protected $casts = [
        'entry_criteria' => 'array',
        'exit_criteria' => 'array',
        'settings' => 'array',
        'auto_enroll' => 'boolean',
        'allow_re_enrollment' => 'boolean',
    ];

    protected $appends = ['steps_count', 'active_enrollments_count'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(CadenceStep::class)->orderBy('step_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CadenceEnrollment::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(CadenceMetric::class);
    }

    public function getStepsCountAttribute(): int
    {
        return $this->steps()->count();
    }

    public function getActiveEnrollmentsCountAttribute(): int
    {
        return $this->enrollments()->where('status', 'active')->count();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canEnroll(): bool
    {
        return $this->isActive() && $this->steps()->where('is_active', true)->exists();
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }
}
