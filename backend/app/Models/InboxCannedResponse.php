<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxCannedResponse extends Model
{
    protected $fillable = [
        'inbox_id',
        'name',
        'shortcut',
        'category',
        'subject',
        'body',
        'attachments',
        'created_by',
        'is_active',
        'use_count',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_active' => 'boolean',
    ];

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'inbox_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('inbox_id');
    }

    public function scopeForInbox($query, int $inboxId)
    {
        return $query->where(function ($q) use ($inboxId) {
            $q->whereNull('inbox_id')
              ->orWhere('inbox_id', $inboxId);
        });
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByShortcut($query, string $shortcut)
    {
        return $query->where('shortcut', $shortcut);
    }

    public function isGlobal(): bool
    {
        return $this->inbox_id === null;
    }

    public function incrementUseCount(): void
    {
        $this->increment('use_count');
    }

    public function render(array $variables = []): string
    {
        $body = $this->body;

        foreach ($variables as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return $body;
    }
}
