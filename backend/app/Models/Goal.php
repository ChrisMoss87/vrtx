<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_TEAM = 'team';
    public const TYPE_COMPANY = 'company';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ACHIEVED = 'achieved';
    public const STATUS_MISSED = 'missed';
    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'name',
        'description',
        'goal_type',
        'user_id',
        'team_id',
        'metric_type',
        'metric_field',
        'module_api_name',
        'target_value',
        'currency',
        'start_date',
        'end_date',
        'current_value',
        'attainment_percent',
        'status',
        'achieved_at',
        'created_by',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'attainment_percent' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'achieved_at' => 'datetime',
    ];

    protected $appends = ['days_remaining', 'progress_percent', 'is_overdue', 'gap_to_target'];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(GoalMilestone::class)->orderBy('display_order');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(GoalProgressLog::class)->orderByDesc('log_date');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('goal_type', $type);
    }

    public function scopeCurrent($query)
    {
        $today = Carbon::today();
        return $query->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today);
    }

    // Computed attributes
    public function getDaysRemainingAttribute(): int
    {
        $today = Carbon::today();
        if ($today > $this->end_date) {
            return 0;
        }
        return $today->diffInDays($this->end_date);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->target_value <= 0) {
            return 0;
        }
        return min(100, round(($this->current_value / $this->target_value) * 100, 1));
    }

    public function getIsOverdueAttribute(): bool
    {
        return Carbon::today() > $this->end_date && $this->status === self::STATUS_IN_PROGRESS;
    }

    public function getGapToTargetAttribute(): float
    {
        return max(0, $this->target_value - $this->current_value);
    }

    public function getNextMilestoneAttribute(): ?GoalMilestone
    {
        return $this->milestones()
            ->where('is_achieved', false)
            ->orderBy('target_value')
            ->first();
    }

    // Methods
    public function recalculate(): void
    {
        if ($this->target_value > 0) {
            $this->attainment_percent = round(($this->current_value / $this->target_value) * 100, 2);
        } else {
            $this->attainment_percent = 0;
        }

        // Check if achieved
        if ($this->current_value >= $this->target_value && $this->status === self::STATUS_IN_PROGRESS) {
            $this->status = self::STATUS_ACHIEVED;
            $this->achieved_at = now();
        }

        $this->save();

        // Check and update milestones
        $this->updateMilestones();
    }

    public function updateProgress(float $newValue, ?string $source = null, ?int $sourceRecordId = null): void
    {
        $changeAmount = $newValue - $this->current_value;

        $this->current_value = $newValue;
        $this->recalculate();

        // Log the progress
        if ($changeAmount != 0) {
            $this->progressLogs()->create([
                'log_date' => now()->toDateString(),
                'value' => $newValue,
                'change_amount' => $changeAmount,
                'change_source' => $source,
                'source_record_id' => $sourceRecordId,
            ]);
        }
    }

    public function addProgress(float $amount, ?string $source = null, ?int $sourceRecordId = null): void
    {
        $this->updateProgress($this->current_value + $amount, $source, $sourceRecordId);
    }

    protected function updateMilestones(): void
    {
        $this->milestones()
            ->where('is_achieved', false)
            ->where('target_value', '<=', $this->current_value)
            ->update([
                'is_achieved' => true,
                'achieved_at' => now(),
            ]);
    }

    public function markAsMissed(): void
    {
        $this->status = self::STATUS_MISSED;
        $this->save();
    }

    public function pause(): void
    {
        $this->status = self::STATUS_PAUSED;
        $this->save();
    }

    public function resume(): void
    {
        if ($this->status === self::STATUS_PAUSED) {
            $this->status = self::STATUS_IN_PROGRESS;
            $this->save();
        }
    }

    public static function getGoalTypes(): array
    {
        return [
            self::TYPE_INDIVIDUAL => 'Individual',
            self::TYPE_TEAM => 'Team',
            self::TYPE_COMPANY => 'Company',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_ACHIEVED => 'Achieved',
            self::STATUS_MISSED => 'Missed',
            self::STATUS_PAUSED => 'Paused',
        ];
    }
}
