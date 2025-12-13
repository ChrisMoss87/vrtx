<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WhatsappConnection extends Model
{
    protected $fillable = [
        'name',
        'phone_number_id',
        'waba_id',
        'access_token',
        'display_phone_number',
        'verified_name',
        'quality_rating',
        'messaging_limit',
        'webhook_verify_token',
        'is_active',
        'is_verified',
        'settings',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'settings' => 'array',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $connection) {
            if (!$connection->webhook_verify_token) {
                $connection->webhook_verify_token = Str::random(32);
            }
        });
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsappTemplate::class, 'connection_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class, 'connection_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'connection_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function getDecryptedAccessTokenAttribute(): string
    {
        return decrypt($this->access_token);
    }

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = encrypt($value);
    }

    public function getQualityBadgeAttribute(): string
    {
        return match ($this->quality_rating) {
            'GREEN' => 'success',
            'YELLOW' => 'warning',
            'RED' => 'destructive',
            default => 'secondary',
        };
    }

    public function canSendMessages(): bool
    {
        return $this->is_active && $this->is_verified && $this->quality_rating !== 'RED';
    }
}
