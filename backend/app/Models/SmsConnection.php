<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'phone_number',
        'account_sid',
        'auth_token',
        'messaging_service_sid',
        'is_active',
        'is_verified',
        'capabilities',
        'settings',
        'daily_limit',
        'monthly_limit',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'capabilities' => 'array',
        'settings' => 'array',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'auth_token',
        'account_sid',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class, 'connection_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(SmsCampaign::class, 'connection_id');
    }

    public function optOuts(): HasMany
    {
        return $this->hasMany(SmsOptOut::class, 'connection_id');
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

    public function getDecryptedAuthToken(): string
    {
        return decrypt($this->auth_token);
    }

    public function setAuthTokenAttribute($value): void
    {
        $this->attributes['auth_token'] = encrypt($value);
    }

    public function canSendSms(): bool
    {
        $capabilities = $this->capabilities ?? [];
        return in_array('sms', $capabilities);
    }

    public function canSendMms(): bool
    {
        $capabilities = $this->capabilities ?? [];
        return in_array('mms', $capabilities);
    }

    public function getTodayMessageCount(): int
    {
        return $this->messages()
            ->whereDate('created_at', today())
            ->where('direction', 'outbound')
            ->count();
    }

    public function getMonthMessageCount(): int
    {
        return $this->messages()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('direction', 'outbound')
            ->count();
    }

    public function isWithinDailyLimit(): bool
    {
        return $this->getTodayMessageCount() < $this->daily_limit;
    }

    public function isWithinMonthlyLimit(): bool
    {
        return $this->getMonthMessageCount() < $this->monthly_limit;
    }
}
