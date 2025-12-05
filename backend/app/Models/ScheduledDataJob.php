<?php

declare(strict_types=1);

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledDataJob extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_IMPORT = 'import';
    public const TYPE_EXPORT = 'export';

    public const SOURCE_SFTP = 'sftp';
    public const SOURCE_URL = 'url';
    public const SOURCE_EMAIL = 'email';

    public const DESTINATION_SFTP = 'sftp';
    public const DESTINATION_EMAIL = 'email';
    public const DESTINATION_WEBHOOK = 'webhook';

    protected $fillable = [
        'module_id',
        'user_id',
        'name',
        'job_type',
        'cron_expression',
        'is_active',
        'job_config',
        'source_type',
        'source_config',
        'destination_type',
        'destination_config',
        'last_run_at',
        'next_run_at',
        'run_count',
        'success_count',
        'failure_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'job_config' => 'array',
        'source_config' => 'array',
        'destination_config' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'run_count' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'run_count' => 0,
        'success_count' => 0,
        'failure_count' => 0,
    ];

    /**
     * Get the module this job belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this job.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to active jobs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to import jobs.
     */
    public function scopeImports($query)
    {
        return $query->where('job_type', self::TYPE_IMPORT);
    }

    /**
     * Scope to export jobs.
     */
    public function scopeExports($query)
    {
        return $query->where('job_type', self::TYPE_EXPORT);
    }

    /**
     * Scope to jobs due to run.
     */
    public function scopeDue($query)
    {
        return $query->active()
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    /**
     * Check if job is due to run.
     */
    public function isDue(): bool
    {
        return $this->is_active && $this->next_run_at && $this->next_run_at->isPast();
    }

    /**
     * Calculate next run time based on cron expression.
     */
    public function calculateNextRunAt(): ?\DateTimeInterface
    {
        try {
            $cron = new CronExpression($this->cron_expression);
            return $cron->getNextRunDate();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update next run time.
     */
    public function updateNextRunAt(): void
    {
        $this->update([
            'next_run_at' => $this->calculateNextRunAt(),
        ]);
    }

    /**
     * Record a successful run.
     */
    public function recordSuccess(): void
    {
        $this->increment('run_count');
        $this->increment('success_count');
        $this->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRunAt(),
        ]);
    }

    /**
     * Record a failed run.
     */
    public function recordFailure(): void
    {
        $this->increment('run_count');
        $this->increment('failure_count');
        $this->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRunAt(),
        ]);
    }

    /**
     * Get success rate percentage.
     */
    public function getSuccessRate(): int
    {
        if ($this->run_count === 0) {
            return 100;
        }

        return (int) round(($this->success_count / $this->run_count) * 100);
    }

    /**
     * Get human-readable schedule description.
     */
    public function getScheduleDescription(): string
    {
        try {
            $cron = new CronExpression($this->cron_expression);
            // Simple descriptions for common patterns
            $parts = explode(' ', $this->cron_expression);

            if ($this->cron_expression === '0 * * * *') return 'Every hour';
            if ($this->cron_expression === '0 0 * * *') return 'Daily at midnight';
            if ($this->cron_expression === '0 0 * * 0') return 'Weekly on Sunday';
            if ($this->cron_expression === '0 0 1 * *') return 'Monthly on the 1st';

            return $this->cron_expression;
        } catch (\Exception $e) {
            return 'Invalid schedule';
        }
    }
}
