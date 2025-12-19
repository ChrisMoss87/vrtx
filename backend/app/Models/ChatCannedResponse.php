<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatCannedResponse extends Model
{
    protected $fillable = [
        'shortcut',
        'title',
        'content',
        'category',
        'created_by',
        'is_global',
        'usage_count',
    ];

    protected $casts = [
        'is_global' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_global', true)
              ->orWhere('created_by', $userId);
        });
    }

    public function scopeByShortcut($query, string $shortcut)
    {
        $shortcut = ltrim($shortcut, '/');
        return $query->where('shortcut', $shortcut);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('shortcut', 'ilike', "%{$term}%")
              ->orWhere('title', 'ilike', "%{$term}%")
              ->orWhere('content', 'ilike', "%{$term}%");
        });
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function renderContent(array $variables = []): string
    {
        $content = $this->content;

        foreach ($variables as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }

        return $content;
    }
}
