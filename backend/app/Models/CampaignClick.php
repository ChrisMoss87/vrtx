<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CampaignClick extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'campaign_send_id',
        'url',
        'link_name',
        'clicked_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function send(): BelongsTo
    {
        return $this->belongsTo(CampaignSend::class, 'campaign_send_id');
    }
}
