<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    // Common API scopes
    public const SCOPE_RECORDS_READ = 'records:read';
    public const SCOPE_RECORDS_WRITE = 'records:write';
    public const SCOPE_RECORDS_DELETE = 'records:delete';
    public const SCOPE_MODULES_READ = 'modules:read';
    public const SCOPE_MODULES_WRITE = 'modules:write';
    public const SCOPE_USERS_READ = 'users:read';
    public const SCOPE_WEBHOOKS_MANAGE = 'webhooks:manage';
    public const SCOPE_EXPORTS = 'exports';
    public const SCOPE_IMPORTS = 'imports';
    public const SCOPE_WORKFLOWS = 'workflows';

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'prefix',
        'description',
        'scopes',
        'allowed_ips',
        'is_active',
        'last_used_at',
        'last_used_ip',
        'expires_at',
        'rate_limit',
        'request_count',
    ];

    protected $casts = [
        'scopes' => 'array',
        'allowed_ips' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'rate_limit' => 'integer',
        'request_count' => 'integer',
    ];

    protected $hidden = [
        'key',
    ];

    /**
     * Generate a new API key.
     */
    public static function generateKey(): array
    {
        $prefix = 'vrtx_' . Str::random(3);
        $secret = Str::random(32);
        $fullKey = $prefix . '_' . $secret;

        return [
            'prefix' => $prefix,
            'key' => hash('sha256', $fullKey),
            'plain_key' => $fullKey, // Only shown once during creation
        ];
    }

    /**
     * Verify an API key.
     */
    public static function verify(string $plainKey): ?self
    {
        $hashedKey = hash('sha256', $plainKey);

        return static::where('key', $hashedKey)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /**
     * Get the user who owns this API key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get request logs for this API key.
     */
    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /**
     * Check if this key has a specific scope.
     */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        // Check for wildcard scope
        if (in_array('*', $scopes)) {
            return true;
        }

        // Check exact match
        if (in_array($scope, $scopes)) {
            return true;
        }

        // Check for category wildcard (e.g., "records:*" matches "records:read")
        $scopeParts = explode(':', $scope);
        if (count($scopeParts) === 2) {
            return in_array($scopeParts[0] . ':*', $scopes);
        }

        return false;
    }

    /**
     * Check if the request IP is allowed.
     */
    public function isIpAllowed(string $ip): bool
    {
        $allowedIps = $this->allowed_ips ?? [];

        // If no IPs specified, allow all
        if (empty($allowedIps)) {
            return true;
        }

        return in_array($ip, $allowedIps);
    }

    /**
     * Check if the key is valid (active, not expired, not deleted).
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Record API key usage.
     */
    public function recordUsage(string $ip): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'request_count' => $this->request_count + 1,
        ]);
    }

    /**
     * Revoke the API key.
     */
    public function revoke(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Get all available scopes.
     */
    public static function getAvailableScopes(): array
    {
        return [
            self::SCOPE_RECORDS_READ => 'Read records from any module',
            self::SCOPE_RECORDS_WRITE => 'Create and update records',
            self::SCOPE_RECORDS_DELETE => 'Delete records',
            self::SCOPE_MODULES_READ => 'Read module definitions',
            self::SCOPE_MODULES_WRITE => 'Create and modify modules',
            self::SCOPE_USERS_READ => 'Read user information',
            self::SCOPE_WEBHOOKS_MANAGE => 'Manage webhooks',
            self::SCOPE_EXPORTS => 'Create and download exports',
            self::SCOPE_IMPORTS => 'Create and run imports',
            self::SCOPE_WORKFLOWS => 'Manage workflows',
        ];
    }

    /**
     * Scope to active keys.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to non-expired keys.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
