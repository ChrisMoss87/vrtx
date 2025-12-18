<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoProvider extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'provider',
        'api_key',
        'api_secret',
        'access_token',
        'refresh_token',
        'client_id',
        'client_secret',
        'webhook_secret',
        'token_expires_at',
        'is_active',
        'is_verified',
        'settings',
        'scopes',
        'last_synced_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'settings' => 'array',
        'scopes' => 'array',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
        'access_token',
        'refresh_token',
        'client_secret',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'client_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
        ];
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(VideoMeeting::class, 'provider_id');
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
