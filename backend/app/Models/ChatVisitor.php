<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatVisitor extends Model
{
    protected $fillable = [
        'widget_id',
        'contact_id',
        'fingerprint',
        'ip_address',
        'user_agent',
        'country',
        'city',
        'name',
        'email',
        'custom_data',
        'pages_viewed',
        'current_page',
        'referrer',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'custom_data' => 'array',
        'pages_viewed' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function widget(): BelongsTo
    {
        return $this->belongsTo(ChatWidget::class, 'widget_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'contact_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'visitor_id');
    }

    public function scopeByFingerprint($query, string $fingerprint)
    {
        return $query->where('fingerprint', $fingerprint);
    }

    public function recordPageView(string $url, ?string $title = null): void
    {
        $pages = $this->pages_viewed ?? [];
        $pages[] = [
            'url' => $url,
            'title' => $title,
            'timestamp' => now()->toISOString(),
        ];

        // Keep last 50 page views
        if (count($pages) > 50) {
            $pages = array_slice($pages, -50);
        }

        $this->update([
            'pages_viewed' => $pages,
            'current_page' => $url,
            'last_seen_at' => now(),
        ]);
    }

    public function identify(string $email, ?string $name = null): void
    {
        $this->update([
            'email' => $email,
            'name' => $name ?? $this->name,
        ]);

        // Try to link to existing contact
        if (!$this->contact_id) {
            $contact = ModuleRecord::whereHas('module', fn($q) => $q->where('api_name', 'contacts'))
                ->whereJsonContains('data->email', $email)
                ->first();

            if ($contact) {
                $this->update(['contact_id' => $contact->id]);
            }
        }
    }

    public function getDisplayName(): string
    {
        return $this->name ?? $this->email ?? "Visitor #{$this->id}";
    }

    public function getLocation(): ?string
    {
        $parts = array_filter([$this->city, $this->country]);
        return $parts ? implode(', ', $parts) : null;
    }
}
