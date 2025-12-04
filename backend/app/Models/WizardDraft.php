<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class WizardDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wizard_type',
        'reference_id',
        'name',
        'form_data',
        'steps_state',
        'current_step_index',
        'expires_at',
    ];

    protected $casts = [
        'form_data' => 'array',
        'steps_state' => 'array',
        'current_step_index' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the draft.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include drafts for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include drafts of a specific wizard type.
     */
    public function scopeOfType(Builder $query, string $wizardType): Builder
    {
        return $query->where('wizard_type', $wizardType);
    }

    /**
     * Scope a query to only include drafts for a specific reference.
     */
    public function scopeForReference(Builder $query, string $referenceId): Builder
    {
        return $query->where('reference_id', $referenceId);
    }

    /**
     * Scope a query to only include non-expired drafts.
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', Carbon::now());
        });
    }

    /**
     * Scope a query to only include expired drafts.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }

    /**
     * Check if the draft is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Get a display name for the draft.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->name) {
            return $this->name;
        }

        return 'Draft from ' . $this->created_at->format('M j, Y g:i A');
    }

    /**
     * Get the completion percentage of the draft.
     */
    public function getCompletionPercentageAttribute(): int
    {
        $stepsState = $this->steps_state ?? [];

        if (empty($stepsState)) {
            return 0;
        }

        $completedSteps = collect($stepsState)->filter(function ($step) {
            return $step['isComplete'] ?? false;
        })->count();

        return (int) round(($completedSteps / count($stepsState)) * 100);
    }

    /**
     * Update the draft with new form data and step state.
     */
    public function updateDraft(array $formData, array $stepsState, int $currentStepIndex): self
    {
        $this->update([
            'form_data' => $formData,
            'steps_state' => $stepsState,
            'current_step_index' => $currentStepIndex,
        ]);

        return $this;
    }

    /**
     * Set draft expiration.
     */
    public function expiresIn(int $days = 30): self
    {
        $this->update([
            'expires_at' => Carbon::now()->addDays($days),
        ]);

        return $this;
    }

    /**
     * Remove expiration from draft (make permanent until manually deleted).
     */
    public function makePermanent(): self
    {
        $this->update([
            'expires_at' => null,
        ]);

        return $this;
    }

    /**
     * Delete all expired drafts for cleanup.
     */
    public static function cleanupExpired(): int
    {
        return static::expired()->delete();
    }
}