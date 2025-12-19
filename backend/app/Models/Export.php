<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Export extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    public const FILE_TYPE_CSV = 'csv';
    public const FILE_TYPE_XLSX = 'xlsx';
    public const FILE_TYPE_PDF = 'pdf';

    protected $fillable = [
        'module_id',
        'user_id',
        'name',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'status',
        'total_records',
        'exported_records',
        'selected_fields',
        'filters',
        'sorting',
        'export_options',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
        'download_count',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_records' => 'integer',
        'exported_records' => 'integer',
        'selected_fields' => 'array',
        'filters' => 'array',
        'sorting' => 'array',
        'export_options' => 'array',
        'download_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'total_records' => 0,
        'exported_records' => 0,
        'download_count' => 0,
    ];

    /**
     * Get the module this export belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this export.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to pending exports.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to completed exports.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to expired exports.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', '!=', self::STATUS_EXPIRED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Check if export is available for download.
     */
    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->file_path
            && (!$this->expires_at || $this->expires_at->isFuture());
    }

    /**
     * Check if export has expired.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentage(): int
    {
        if ($this->total_records === 0) {
            return 0;
        }

        return (int) round(($this->exported_records / $this->total_records) * 100);
    }

    /**
     * Get download URL.
     */
    public function getDownloadUrl(): ?string
    {
        if (!$this->isDownloadable()) {
            return null;
        }

        return route('exports.download', $this->id);
    }

    /**
     * Increment download count.
     */
    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    /**
     * Mark export as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark export as completed.
     */
    public function markAsCompleted(string $filePath, string $fileName, int $fileSize, int $exportedRecords): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'exported_records' => $exportedRecords,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7), // Default 7 day expiry
        ]);
    }

    /**
     * Mark export as failed.
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
     * Mark export as expired and clean up file.
     */
    public function markAsExpired(): void
    {
        if ($this->file_path && Storage::disk('exports')->exists($this->file_path)) {
            Storage::disk('exports')->delete($this->file_path);
        }

        $this->update([
            'status' => self::STATUS_EXPIRED,
            'file_path' => null,
        ]);
    }
}
