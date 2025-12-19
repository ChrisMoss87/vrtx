<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsOptOut extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number',
        'type',
        'reason',
        'connection_id',
        'opted_out_at',
        'opted_in_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'opted_out_at' => 'datetime',
        'opted_in_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SmsConnection::class, 'connection_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPhone($query, string $phone)
    {
        return $query->where('phone_number', $phone);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if a phone number is opted out
     */
    public static function isOptedOut(string $phone, string $type = 'all'): bool
    {
        return static::where('phone_number', static::normalizePhone($phone))
            ->where('is_active', true)
            ->where(function ($q) use ($type) {
                $q->where('type', 'all')
                  ->orWhere('type', $type);
            })
            ->exists();
    }

    /**
     * Opt out a phone number
     */
    public static function optOut(string $phone, string $type = 'all', ?string $reason = null, ?int $connectionId = null): self
    {
        return static::updateOrCreate(
            [
                'phone_number' => static::normalizePhone($phone),
                'type' => $type,
            ],
            [
                'reason' => $reason,
                'connection_id' => $connectionId,
                'opted_out_at' => now(),
                'opted_in_at' => null,
                'is_active' => true,
            ]
        );
    }

    /**
     * Opt in a phone number (resubscribe)
     */
    public static function optIn(string $phone, string $type = 'all'): bool
    {
        return static::where('phone_number', static::normalizePhone($phone))
            ->where('type', $type)
            ->update([
                'is_active' => false,
                'opted_in_at' => now(),
            ]) > 0;
    }

    /**
     * Normalize phone number for consistent storage
     */
    public static function normalizePhone(string $phone): string
    {
        // Remove all non-digit characters except leading +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Ensure it starts with +
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
