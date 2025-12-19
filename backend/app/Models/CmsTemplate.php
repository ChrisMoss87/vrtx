<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsTemplate extends Model
{
    use SoftDeletes;

    public const TYPE_PAGE = 'page';
    public const TYPE_EMAIL = 'email';
    public const TYPE_FORM = 'form';
    public const TYPE_LANDING = 'landing';
    public const TYPE_BLOG = 'blog';
    public const TYPE_PARTIAL = 'partial';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'content',
        'settings',
        'thumbnail',
        'is_system',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'content' => 'array',
        'settings' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'created_by' => 'integer',
    ];

    protected $attributes = [
        'type' => self::TYPE_PAGE,
        'is_system' => false,
        'is_active' => true,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CmsPage::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeUserTemplates($query)
    {
        return $query->where('is_system', false);
    }

    public function scopeSystemTemplates($query)
    {
        return $query->where('is_system', true);
    }

    public function duplicate(?int $userId = null): self
    {
        $copy = $this->replicate();
        $copy->name = $this->name . ' (Copy)';
        $copy->slug = $this->slug . '-copy-' . time();
        $copy->is_system = false;
        $copy->created_by = $userId ?? $this->created_by;
        $copy->save();

        return $copy;
    }

    public function canDelete(): bool
    {
        return !$this->is_system;
    }

    public function getUsageCount(): int
    {
        return $this->pages()->count();
    }
}
