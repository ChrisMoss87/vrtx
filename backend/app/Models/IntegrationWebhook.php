<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class IntegrationWebhook extends Model
{
    protected $fillable = [
        'connection_id',
        'webhook_id',
        'endpoint_url',
        'endpoint_secret',
        'subscribed_events',
        'status',
        'verified_at',
        'last_received_at',
        'received_count',
    ];

    protected $casts = [
        'subscribed_events' => 'array',
        'verified_at' => 'datetime',
        'last_received_at' => 'datetime',
    ];

    protected $hidden = [
        'endpoint_secret',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationWebhookLog::class, 'webhook_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getEndpointSecretAttribute($value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setEndpointSecretAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['endpoint_secret'] = null;
            return;
        }

        $this->attributes['endpoint_secret'] = Crypt::encryptString($value);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isSubscribedTo(string $event): bool
    {
        return in_array($event, $this->subscribed_events ?? []);
    }

    public function markReceived(): void
    {
        $this->update([
            'last_received_at' => now(),
            'received_count' => $this->received_count + 1,
        ]);
    }

    public function verify(): void
    {
        $this->update([
            'status' => 'active',
            'verified_at' => now(),
        ]);
    }
}
