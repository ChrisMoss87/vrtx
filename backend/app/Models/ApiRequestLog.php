<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'api_key_id',
        'method',
        'path',
        'status_code',
        'ip_address',
        'user_agent',
        'response_time_ms',
        'request_headers',
        'response_summary',
        'created_at',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'request_headers' => 'array',
        'response_summary' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the API key this log belongs to.
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Log an API request.
     */
    public static function logRequest(
        ?int $apiKeyId,
        string $method,
        string $path,
        int $statusCode,
        string $ipAddress,
        ?string $userAgent,
        int $responseTimeMs,
        ?array $requestHeaders = null,
        ?array $responseSummary = null
    ): self {
        return static::create([
            'api_key_id' => $apiKeyId,
            'method' => $method,
            'path' => substr($path, 0, 500),
            'status_code' => $statusCode,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'response_time_ms' => $responseTimeMs,
            'request_headers' => $requestHeaders,
            'response_summary' => $responseSummary,
            'created_at' => now(),
        ]);
    }

    /**
     * Scope to requests for a specific API key.
     */
    public function scopeForApiKey($query, int $apiKeyId)
    {
        return $query->where('api_key_id', $apiKeyId);
    }

    /**
     * Scope to requests within a time range.
     */
    public function scopeInTimeRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Get error requests (4xx or 5xx status codes).
     */
    public function scopeErrors($query)
    {
        return $query->where('status_code', '>=', 400);
    }
}
