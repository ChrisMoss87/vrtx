<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallProvider extends Model
{
    protected $fillable = [
        'name',
        'provider',
        'api_key',
        'api_secret',
        'auth_token',
        'account_sid',
        'phone_number',
        'webhook_url',
        'is_active',
        'is_verified',
        'recording_enabled',
        'transcription_enabled',
        'settings',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'recording_enabled' => 'boolean',
        'transcription_enabled' => 'boolean',
        'settings' => 'array',
        'last_synced_at' => 'datetime',
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'auth_token' => 'encrypted',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
        'auth_token',
    ];

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class, 'provider_id');
    }

    public function queues(): HasMany
    {
        return $this->hasMany(CallQueue::class, 'provider_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function isTwilio(): bool
    {
        return $this->provider === 'twilio';
    }

    public function isVonage(): bool
    {
        return $this->provider === 'vonage';
    }

    public function isRingCentral(): bool
    {
        return $this->provider === 'ringcentral';
    }

    public function isAircall(): bool
    {
        return $this->provider === 'aircall';
    }

    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }
}
