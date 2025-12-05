<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Import extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_IMPORTING = 'importing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const FILE_TYPE_CSV = 'csv';
    public const FILE_TYPE_XLSX = 'xlsx';
    public const FILE_TYPE_XLS = 'xls';

    public const DUPLICATE_SKIP = 'skip';
    public const DUPLICATE_UPDATE = 'update';
    public const DUPLICATE_CREATE = 'create';

    protected $fillable = [
        'module_id',
        'user_id',
        'name',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'status',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'skipped_rows',
        'column_mapping',
        'import_options',
        'validation_errors',
        'field_transformations',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'successful_rows' => 'integer',
        'failed_rows' => 'integer',
        'skipped_rows' => 'integer',
        'column_mapping' => 'array',
        'import_options' => 'array',
        'validation_errors' => 'array',
        'field_transformations' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'total_rows' => 0,
        'processed_rows' => 0,
        'successful_rows' => 0,
        'failed_rows' => 0,
        'skipped_rows' => 0,
    ];

    /**
     * Get the module this import belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this import.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the import rows.
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->orderBy('row_number');
    }

    /**
     * Get failed rows.
     */
    public function failedRows(): HasMany
    {
        return $this->rows()->where('status', ImportRow::STATUS_FAILED);
    }

    /**
     * Get successful rows.
     */
    public function successfulRows(): HasMany
    {
        return $this->rows()->where('status', ImportRow::STATUS_SUCCESS);
    }

    /**
     * Scope by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to pending imports.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to in-progress imports.
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [
            self::STATUS_VALIDATING,
            self::STATUS_VALIDATED,
            self::STATUS_IMPORTING,
        ]);
    }

    /**
     * Check if import is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Check if import can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return !$this->isTerminal();
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentage(): int
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }

    /**
     * Get duplicate handling option.
     */
    public function getDuplicateHandling(): string
    {
        return $this->import_options['duplicate_handling'] ?? self::DUPLICATE_SKIP;
    }

    /**
     * Get duplicate check field.
     */
    public function getDuplicateCheckField(): ?string
    {
        return $this->import_options['duplicate_check_field'] ?? null;
    }

    /**
     * Increment processed count.
     */
    public function incrementProcessed(string $status): void
    {
        $this->increment('processed_rows');

        match ($status) {
            ImportRow::STATUS_SUCCESS => $this->increment('successful_rows'),
            ImportRow::STATUS_FAILED => $this->increment('failed_rows'),
            ImportRow::STATUS_SKIPPED => $this->increment('skipped_rows'),
            default => null,
        };
    }

    /**
     * Mark import as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_IMPORTING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark import as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark import as failed.
     */
    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark import as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }
}
