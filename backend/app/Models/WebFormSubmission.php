<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebFormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'web_form_id',
        'record_id',
        'submission_data',
        'ip_address',
        'user_agent',
        'referrer',
        'utm_params',
        'status',
        'error_message',
        'submitted_at',
    ];

    protected $casts = [
        'submission_data' => 'array',
        'utm_params' => 'array',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'processed',
    ];

    /**
     * Submission status constants.
     */
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SPAM = 'spam';
    public const STATUS_PENDING = 'pending';

    /**
     * Get the form this submission belongs to.
     */
    public function webForm(): BelongsTo
    {
        return $this->belongsTo(WebForm::class);
    }

    /**
     * Get the created record.
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'record_id');
    }

    /**
     * Scope to processed submissions.
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    /**
     * Scope to failed submissions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to spam submissions.
     */
    public function scopeSpam($query)
    {
        return $query->where('status', self::STATUS_SPAM);
    }

    /**
     * Check if submission was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    /**
     * Check if submission was spam.
     */
    public function isSpam(): bool
    {
        return $this->status === self::STATUS_SPAM;
    }

    /**
     * Get a specific field value from submission data.
     */
    public function getFieldValue(string $fieldName): mixed
    {
        return data_get($this->submission_data, $fieldName);
    }

    /**
     * Get UTM parameter value.
     */
    public function getUtmParam(string $param): ?string
    {
        return $this->utm_params[$param] ?? null;
    }

    /**
     * Get the source (utm_source or referrer).
     */
    public function getSource(): ?string
    {
        return $this->getUtmParam('utm_source') ?? $this->referrer;
    }
}
