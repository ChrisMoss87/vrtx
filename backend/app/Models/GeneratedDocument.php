<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneratedDocument extends Model
{
    use HasFactory;

    public const STATUS_GENERATED = 'generated';
    public const STATUS_SENT = 'sent';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_SIGNED = 'signed';

    public const STATUSES = [
        self::STATUS_GENERATED,
        self::STATUS_SENT,
        self::STATUS_VIEWED,
        self::STATUS_SIGNED,
    ];

    protected $fillable = [
        'template_id',
        'record_type',
        'record_id',
        'name',
        'output_format',
        'file_path',
        'file_url',
        'file_size',
        'merged_data',
        'status',
        'created_by',
    ];

    protected $casts = [
        'merged_data' => 'array',
        'file_size' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_GENERATED,
        'output_format' => 'pdf',
    ];

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(DocumentSendLog::class, 'document_id');
    }

    public function signatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class, 'document_id');
    }

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForRecord($query, string $recordType, int $recordId)
    {
        return $query->where('record_type', $recordType)
                     ->where('record_id', $recordId);
    }

    // Helpers
    public function markAsSent(): void
    {
        $this->status = self::STATUS_SENT;
        $this->save();
    }

    public function markAsViewed(): void
    {
        if ($this->status === self::STATUS_SENT) {
            $this->status = self::STATUS_VIEWED;
            $this->save();
        }
    }

    public function markAsSigned(): void
    {
        $this->status = self::STATUS_SIGNED;
        $this->save();
    }
}
