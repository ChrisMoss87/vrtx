<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'record_id_a',
        'record_id_b',
        'match_score',
        'matched_rules',
        'status',
        'reviewed_by',
        'reviewed_at',
        'dismiss_reason',
    ];

    protected $casts = [
        'record_id_a' => 'integer',
        'record_id_b' => 'integer',
        'match_score' => 'decimal:4',
        'matched_rules' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_MERGED = 'merged';
    public const STATUS_DISMISSED = 'dismissed';

    /**
     * Get the module this candidate belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who reviewed this candidate.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get Record A.
     */
    public function recordA(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'record_id_a');
    }

    /**
     * Get Record B.
     */
    public function recordB(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'record_id_b');
    }

    /**
     * Scope to pending candidates only.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to candidates for a specific module.
     */
    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Scope ordered by match score (highest first).
     */
    public function scopeHighestMatch($query)
    {
        return $query->orderBy('match_score', 'desc');
    }

    /**
     * Mark as merged.
     */
    public function markAsMerged(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_MERGED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Mark as dismissed.
     */
    public function markAsDismissed(int $userId, ?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_DISMISSED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'dismiss_reason' => $reason,
        ]);
    }
}
