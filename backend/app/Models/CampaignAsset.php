<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CampaignAsset extends Model
{
    use HasFactory, BelongsToTenant;

    public const TYPE_EMAIL = 'email';
    public const TYPE_IMAGE = 'image';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_LANDING_PAGE = 'landing_page';

    protected $fillable = [
        'campaign_id',
        'type',
        'name',
        'description',
        'subject',
        'content',
        'metadata',
        'version',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'metadata' => '{}',
        'version' => 1,
        'is_active' => true,
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function isEmail(): bool
    {
        return $this->type === self::TYPE_EMAIL;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEmails($query)
    {
        return $query->where('type', self::TYPE_EMAIL);
    }
}
