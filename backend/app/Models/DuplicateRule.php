<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'name',
        'description',
        'is_active',
        'action',
        'conditions',
        'priority',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'conditions' => 'array',
        'priority' => 'integer',
    ];

    /**
     * Action constants
     */
    public const ACTION_WARN = 'warn';
    public const ACTION_BLOCK = 'block';

    /**
     * Match type constants
     */
    public const MATCH_EXACT = 'exact';
    public const MATCH_FUZZY = 'fuzzy';
    public const MATCH_PHONETIC = 'phonetic';
    public const MATCH_EMAIL_DOMAIN = 'email_domain';

    /**
     * Get the module this rule belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this rule.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to active rules only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to rules for a specific module.
     */
    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Scope ordered by priority (highest first).
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}
