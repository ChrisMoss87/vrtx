<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'import_id',
        'row_number',
        'original_data',
        'mapped_data',
        'status',
        'record_id',
        'errors',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'original_data' => 'array',
        'mapped_data' => 'array',
        'record_id' => 'integer',
        'errors' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * Get the import this row belongs to.
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /**
     * Get the created record (if any).
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'record_id');
    }

    /**
     * Check if row has errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get error messages.
     */
    public function getErrorMessages(): array
    {
        return $this->errors ?? [];
    }

    /**
     * Mark as success with created record.
     */
    public function markAsSuccess(?int $recordId = null): void
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'record_id' => $recordId,
        ]);
    }

    /**
     * Mark as failed with errors.
     */
    public function markAsFailed(array $errors): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'errors' => $errors,
        ]);
    }

    /**
     * Mark as skipped.
     */
    public function markAsSkipped(string $reason = 'Duplicate record'): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'errors' => ['skipped' => $reason],
        ]);
    }

    /**
     * Scope by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to failed rows.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to successful rows.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }
}
