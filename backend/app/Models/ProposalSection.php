<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProposalSection extends Model
{
    public const TYPE_COVER = 'cover';
    public const TYPE_EXECUTIVE_SUMMARY = 'executive_summary';
    public const TYPE_SCOPE = 'scope';
    public const TYPE_PRICING = 'pricing';
    public const TYPE_TIMELINE = 'timeline';
    public const TYPE_TERMS = 'terms';
    public const TYPE_TEAM = 'team';
    public const TYPE_CASE_STUDY = 'case_study';
    public const TYPE_CUSTOM = 'custom';

    public const TYPES = [
        self::TYPE_COVER,
        self::TYPE_EXECUTIVE_SUMMARY,
        self::TYPE_SCOPE,
        self::TYPE_PRICING,
        self::TYPE_TIMELINE,
        self::TYPE_TERMS,
        self::TYPE_TEAM,
        self::TYPE_CASE_STUDY,
        self::TYPE_CUSTOM,
    ];

    protected $fillable = [
        'proposal_id',
        'section_type',
        'title',
        'content',
        'settings',
        'display_order',
        'is_visible',
        'is_locked',
    ];

    protected $casts = [
        'settings' => 'array',
        'display_order' => 'integer',
        'is_visible' => 'boolean',
        'is_locked' => 'boolean',
    ];

    protected $attributes = [
        'display_order' => 0,
        'is_visible' => true,
        'is_locked' => false,
    ];

    // Relationships
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function pricingItems(): HasMany
    {
        return $this->hasMany(ProposalPricingItem::class, 'section_id')->orderBy('display_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProposalComment::class, 'section_id');
    }

    // Scopes
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('section_type', $type);
    }

    // Helpers
    public function moveUp(): void
    {
        $previous = $this->proposal->sections()
            ->where('display_order', '<', $this->display_order)
            ->orderByDesc('display_order')
            ->first();

        if ($previous) {
            $temp = $this->display_order;
            $this->display_order = $previous->display_order;
            $previous->display_order = $temp;

            $this->save();
            $previous->save();
        }
    }

    public function moveDown(): void
    {
        $next = $this->proposal->sections()
            ->where('display_order', '>', $this->display_order)
            ->orderBy('display_order')
            ->first();

        if ($next) {
            $temp = $this->display_order;
            $this->display_order = $next->display_order;
            $next->display_order = $temp;

            $this->save();
            $next->save();
        }
    }
}
