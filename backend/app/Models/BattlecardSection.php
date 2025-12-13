<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BattlecardSection extends Model
{
    protected $fillable = [
        'competitor_id',
        'section_type',
        'content',
        'display_order',
        'created_by',
    ];

    public const TYPE_STRENGTHS = 'strengths';
    public const TYPE_WEAKNESSES = 'weaknesses';
    public const TYPE_OUR_ADVANTAGES = 'our_advantages';
    public const TYPE_PRICING = 'pricing';
    public const TYPE_RESOURCES = 'resources';
    public const TYPE_WIN_STORIES = 'win_stories';

    public static function getTypes(): array
    {
        return [
            self::TYPE_STRENGTHS => 'Their Strengths',
            self::TYPE_WEAKNESSES => 'Their Weaknesses',
            self::TYPE_OUR_ADVANTAGES => 'Our Advantages',
            self::TYPE_PRICING => 'Pricing Information',
            self::TYPE_RESOURCES => 'Resources & Links',
            self::TYPE_WIN_STORIES => 'Win Stories',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabel(): string
    {
        return self::getTypes()[$this->section_type] ?? $this->section_type;
    }

    public function getContentLines(): array
    {
        return array_filter(
            array_map('trim', explode("\n", $this->content)),
            fn ($line) => !empty($line)
        );
    }
}
