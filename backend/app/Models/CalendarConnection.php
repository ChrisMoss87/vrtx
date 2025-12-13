<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'calendar_id',
        'calendar_name',
        'is_primary',
        'sync_enabled',
        'last_synced_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_primary' => 'boolean',
        'sync_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Supported calendar providers.
     */
    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_OUTLOOK = 'outlook';
    public const PROVIDER_APPLE = 'apple';

    /**
     * Get the user who owns this connection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get cached events for this calendar.
     */
    public function cachedEvents(): HasMany
    {
        return $this->hasMany(CalendarEventCache::class);
    }

    /**
     * Check if the token is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }
        return $this->token_expires_at->isPast();
    }

    /**
     * Check if token needs refresh (expires within 5 minutes).
     */
    public function getNeedsRefreshAttribute(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }
        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    /**
     * Update the access token.
     */
    public function updateToken(string $accessToken, ?string $refreshToken = null, ?\DateTime $expiresAt = null): void
    {
        $data = ['access_token' => $accessToken];

        if ($refreshToken) {
            $data['refresh_token'] = $refreshToken;
        }
        if ($expiresAt) {
            $data['token_expires_at'] = $expiresAt;
        }

        $this->update($data);
    }

    /**
     * Mark as synced.
     */
    public function markSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }

    /**
     * Scope for active connections.
     */
    public function scopeActive($query)
    {
        return $query->where('sync_enabled', true);
    }

    /**
     * Scope by provider.
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Get the provider display name.
     */
    public function getProviderNameAttribute(): string
    {
        return match ($this->provider) {
            self::PROVIDER_GOOGLE => 'Google Calendar',
            self::PROVIDER_OUTLOOK => 'Microsoft Outlook',
            self::PROVIDER_APPLE => 'Apple Calendar',
            default => ucfirst($this->provider),
        };
    }
}
