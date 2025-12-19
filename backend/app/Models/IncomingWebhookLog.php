<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingWebhookLog extends Model
{
    public $timestamps = false;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_INVALID = 'invalid';

    protected $fillable = [
        'incoming_webhook_id',
        'payload',
        'status',
        'record_id',
        'error_message',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'record_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the incoming webhook this log belongs to.
     */
    public function incomingWebhook(): BelongsTo
    {
        return $this->belongsTo(IncomingWebhook::class);
    }

    /**
     * Get the created/updated record.
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'record_id');
    }

    /**
     * Log a successful webhook.
     */
    public static function logSuccess(
        int $incomingWebhookId,
        array $payload,
        ?int $recordId,
        string $ipAddress
    ): self {
        return static::create([
            'incoming_webhook_id' => $incomingWebhookId,
            'payload' => $payload,
            'status' => self::STATUS_SUCCESS,
            'record_id' => $recordId,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }

    /**
     * Log a failed webhook.
     */
    public static function logFailed(
        int $incomingWebhookId,
        array $payload,
        string $errorMessage,
        string $ipAddress
    ): self {
        return static::create([
            'incoming_webhook_id' => $incomingWebhookId,
            'payload' => $payload,
            'status' => self::STATUS_FAILED,
            'error_message' => substr($errorMessage, 0, 1000),
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }

    /**
     * Log an invalid webhook.
     */
    public static function logInvalid(
        int $incomingWebhookId,
        array $payload,
        string $errorMessage,
        string $ipAddress
    ): self {
        return static::create([
            'incoming_webhook_id' => $incomingWebhookId,
            'payload' => $payload,
            'status' => self::STATUS_INVALID,
            'error_message' => substr($errorMessage, 0, 1000),
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }

    /**
     * Scope to successful logs.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope to failed logs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to recent logs.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
