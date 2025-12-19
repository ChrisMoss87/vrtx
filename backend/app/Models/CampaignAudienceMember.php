<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CampaignAudienceMember extends Model
{
    use HasFactory, BelongsToTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_OPENED = 'opened';
    public const STATUS_CLICKED = 'clicked';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUS_BOUNCED = 'bounced';

    protected $fillable = [
        'campaign_audience_id',
        'record_id',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function audience(): BelongsTo
    {
        return $this->belongsTo(CampaignAudience::class, 'campaign_audience_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'record_id');
    }
}
