<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsAlertSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_id',
        'user_id',
        'channels',
        'is_muted',
        'muted_until',
    ];

    protected $casts = [
        'channels' => 'array',
        'is_muted' => 'boolean',
        'muted_until' => 'datetime',
    ];

    /**
     * Get the alert this subscription belongs to.
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(AnalyticsAlert::class, 'alert_id');
    }

    /**
     * Get the subscribed user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if subscription is currently muted.
     */
    public function isMuted(): bool
    {
        if (!$this->is_muted) {
            return false;
        }

        // If muted until a specific time, check if that time has passed
        if ($this->muted_until && $this->muted_until->isPast()) {
            $this->update(['is_muted' => false, 'muted_until' => null]);
            return false;
        }

        return true;
    }

    /**
     * Mute notifications for this subscription.
     */
    public function mute(?\DateTimeInterface $until = null): void
    {
        $this->update([
            'is_muted' => true,
            'muted_until' => $until,
        ]);
    }

    /**
     * Unmute notifications for this subscription.
     */
    public function unmute(): void
    {
        $this->update([
            'is_muted' => false,
            'muted_until' => null,
        ]);
    }

    /**
     * Get effective channels (subscription override or alert default).
     */
    public function getEffectiveChannels(): array
    {
        return $this->channels ?? $this->alert->getChannels();
    }
}
