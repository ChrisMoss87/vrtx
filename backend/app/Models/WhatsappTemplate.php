<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappTemplate extends Model
{
    protected $fillable = [
        'connection_id',
        'template_id',
        'name',
        'language',
        'category',
        'status',
        'rejection_reason',
        'components',
        'example',
        'created_by',
        'submitted_at',
        'approved_at',
    ];

    protected $casts = [
        'components' => 'array',
        'example' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsappConnection::class, 'connection_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'template_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['APPROVED', 'PENDING']);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function isUsable(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function getHeaderComponentAttribute(): ?array
    {
        return collect($this->components ?? [])->firstWhere('type', 'HEADER');
    }

    public function getBodyComponentAttribute(): ?array
    {
        return collect($this->components ?? [])->firstWhere('type', 'BODY');
    }

    public function getFooterComponentAttribute(): ?array
    {
        return collect($this->components ?? [])->firstWhere('type', 'FOOTER');
    }

    public function getButtonsComponentAttribute(): ?array
    {
        return collect($this->components ?? [])->firstWhere('type', 'BUTTONS');
    }

    public function getVariableCountAttribute(): int
    {
        $body = $this->body_component['text'] ?? '';
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
        return count(array_unique($matches[1]));
    }

    public function renderBody(array $variables = []): string
    {
        $body = $this->body_component['text'] ?? '';
        foreach ($variables as $index => $value) {
            $body = str_replace('{{' . ($index + 1) . '}}', $value, $body);
        }
        return $body;
    }
}
