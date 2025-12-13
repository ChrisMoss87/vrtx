<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CampaignConversion extends Model
{
    use HasFactory, BelongsToTenant;

    public const TYPE_LEAD = 'lead';
    public const TYPE_OPPORTUNITY = 'opportunity';
    public const TYPE_DEAL_WON = 'deal_won';
    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'campaign_id',
        'campaign_send_id',
        'record_id',
        'conversion_type',
        'value',
        'metadata',
        'converted_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'metadata' => 'array',
        'converted_at' => 'datetime',
    ];

    protected $attributes = [
        'metadata' => '{}',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function send(): BelongsTo
    {
        return $this->belongsTo(CampaignSend::class, 'campaign_send_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'record_id');
    }
}
