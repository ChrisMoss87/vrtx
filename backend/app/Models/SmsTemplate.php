<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'content',
        'category',
        'is_active',
        'merge_fields',
        'character_count',
        'segment_count',
        'created_by',
        'usage_count',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'merge_fields' => 'array',
        'last_used_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($template) {
            $template->character_count = strlen($template->content);
            $template->segment_count = self::calculateSegments($template->content);
            $template->merge_fields = self::extractMergeFields($template->content);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class, 'template_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(SmsCampaign::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Calculate number of SMS segments needed for the message
     */
    public static function calculateSegments(string $content): int
    {
        $length = strlen($content);

        // Check if message contains non-GSM characters (requires UCS-2 encoding)
        $isUnicode = preg_match('/[^\x00-\x7F]/', $content);

        if ($isUnicode) {
            // UCS-2: 70 chars per segment, 67 for multipart
            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }

        // GSM-7: 160 chars per segment, 153 for multipart
        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

    /**
     * Extract merge fields from content
     */
    public static function extractMergeFields(string $content): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Render template with data
     */
    public function render(array $data): string
    {
        $content = $this->content;

        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
        }

        // Remove any unmatched merge fields
        $content = preg_replace('/\{\{\w+\}\}/', '', $content);

        return trim($content);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }
}
