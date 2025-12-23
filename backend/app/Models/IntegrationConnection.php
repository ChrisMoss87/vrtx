<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Integration\ValueObjects\ConnectionStatus;
use App\Domain\Integration\ValueObjects\IntegrationProvider;
use App\Domain\Integration\ValueObjects\SyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class IntegrationConnection extends Model
{
    protected $fillable = [
        'integration_slug',
        'name',
        'status',
        'credentials',
        'settings',
        'metadata',
        'last_sync_at',
        'sync_status',
        'error_message',
        'connected_by',
        'token_expires_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'metadata' => 'array',
        'last_sync_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'status' => ConnectionStatus::class,
        'sync_status' => SyncStatus::class,
    ];

    protected $hidden = [
        'credentials',
    ];

    // Relationships
    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(IntegrationSyncLog::class, 'connection_id');
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(IntegrationFieldMapping::class, 'connection_id');
    }

    public function entityMappings(): HasMany
    {
        return $this->hasMany(IntegrationEntityMapping::class, 'connection_id');
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(IntegrationWebhook::class, 'connection_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', ConnectionStatus::ACTIVE);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('integration_slug', $provider);
    }

    public function scopeNeedsTokenRefresh($query, int $minutesBeforeExpiry = 5)
    {
        return $query->where('status', ConnectionStatus::ACTIVE)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addMinutes($minutesBeforeExpiry));
    }

    // Accessors
    public function getProviderAttribute(): ?IntegrationProvider
    {
        return IntegrationProvider::tryFrom($this->integration_slug);
    }

    public function getCredentialsAttribute($value): ?array
    {
        if ($value === null) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($value), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    // Mutators
    public function setCredentialsAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['credentials'] = null;
            return;
        }

        $this->attributes['credentials'] = Crypt::encryptString(json_encode($value));
    }

    // Helper methods
    public function isConnected(): bool
    {
        return $this->status === ConnectionStatus::ACTIVE;
    }

    public function hasValidToken(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        if ($this->token_expires_at === null) {
            return true; // API key based, doesn't expire
        }

        return $this->token_expires_at->isFuture();
    }

    public function needsTokenRefresh(): bool
    {
        if ($this->token_expires_at === null) {
            return false;
        }

        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    public function getAccessToken(): ?string
    {
        return $this->credentials['access_token'] ?? null;
    }

    public function getRefreshToken(): ?string
    {
        return $this->credentials['refresh_token'] ?? null;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
    }

    public function markAsActive(): void
    {
        $this->update([
            'status' => ConnectionStatus::ACTIVE,
            'error_message' => null,
        ]);
    }

    public function markAsError(string $message): void
    {
        $this->update([
            'status' => ConnectionStatus::ERROR,
            'error_message' => $message,
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => ConnectionStatus::EXPIRED,
            'error_message' => 'Token has expired and could not be refreshed',
        ]);
    }

    public function disconnect(): void
    {
        $this->update([
            'status' => ConnectionStatus::INACTIVE,
            'credentials' => null,
            'token_expires_at' => null,
        ]);
    }
}
