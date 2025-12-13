<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiEmailDraft extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'tone',
        'context',
        'prompt',
        'original_content',
        'generated_subject',
        'generated_content',
        'model_used',
        'tokens_used',
        'was_used',
    ];

    protected $casts = [
        'context' => 'array',
        'was_used' => 'boolean',
    ];

    // Types
    public const TYPE_COMPOSE = 'compose';
    public const TYPE_REPLY = 'reply';
    public const TYPE_IMPROVE = 'improve';
    public const TYPE_TRANSLATE = 'translate';

    // Tones
    public const TONE_FORMAL = 'formal';
    public const TONE_FRIENDLY = 'friendly';
    public const TONE_URGENT = 'urgent';
    public const TONE_PROFESSIONAL = 'professional';
    public const TONE_CASUAL = 'casual';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectSuggestions(): HasMany
    {
        return $this->hasMany(AiSubjectSuggestion::class, 'draft_id');
    }

    /**
     * Mark as used when email is sent
     */
    public function markAsUsed(): void
    {
        $this->update(['was_used' => true]);
    }

    /**
     * Get available tones
     */
    public static function getTones(): array
    {
        return [
            self::TONE_FORMAL => 'Formal',
            self::TONE_FRIENDLY => 'Friendly',
            self::TONE_URGENT => 'Urgent',
            self::TONE_PROFESSIONAL => 'Professional',
            self::TONE_CASUAL => 'Casual',
        ];
    }
}
