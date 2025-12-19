<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamChatConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'workspace_id',
        'workspace_name',
        'access_token',
        'bot_token',
        'bot_user_id',
        'refresh_token',
        'token_expires_at',
        'webhook_url',
        'is_active',
        'is_verified',
        'scopes',
        'settings',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'scopes' => 'array',
        'settings' => 'array',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'bot_token',
        'refresh_token',
    ];

    public function channels(): HasMany
    {
        return $this->hasMany(TeamChatChannel::class, 'connection_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(TeamChatNotification::class, 'connection_id');
    }

    public function userMappings(): HasMany
    {
        return $this->hasMany(TeamChatUserMapping::class, 'connection_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TeamChatMessage::class, 'connection_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSlack($query)
    {
        return $query->where('provider', 'slack');
    }

    public function scopeTeams($query)
    {
        return $query->where('provider', 'teams');
    }

    public function getDecryptedAccessToken(): string
    {
        return decrypt($this->access_token);
    }

    public function getDecryptedBotToken(): ?string
    {
        return $this->bot_token ? decrypt($this->bot_token) : null;
    }

    public function getDecryptedRefreshToken(): ?string
    {
        return $this->refresh_token ? decrypt($this->refresh_token) : null;
    }

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = encrypt($value);
    }

    public function setBotTokenAttribute($value): void
    {
        $this->attributes['bot_token'] = $value ? encrypt($value) : null;
    }

    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? encrypt($value) : null;
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }
        return $this->token_expires_at->isPast();
    }

    public function isSlack(): bool
    {
        return $this->provider === 'slack';
    }

    public function isTeams(): bool
    {
        return $this->provider === 'teams';
    }
}
