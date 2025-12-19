<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CampaignUnsubscribe extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'email',
        'record_id',
        'campaign_id',
        'reason',
        'unsubscribed_at',
    ];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'record_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Check if an email is unsubscribed
     */
    public static function isUnsubscribed(string $email): bool
    {
        return self::where('email', strtolower($email))->exists();
    }

    /**
     * Unsubscribe an email
     */
    public static function unsubscribe(string $email, ?int $recordId = null, ?int $campaignId = null, ?string $reason = null): self
    {
        return self::firstOrCreate(
            ['email' => strtolower($email)],
            [
                'record_id' => $recordId,
                'campaign_id' => $campaignId,
                'reason' => $reason,
                'unsubscribed_at' => now(),
            ]
        );
    }

    /**
     * Resubscribe an email
     */
    public static function resubscribe(string $email): bool
    {
        return self::where('email', strtolower($email))->delete() > 0;
    }
}
