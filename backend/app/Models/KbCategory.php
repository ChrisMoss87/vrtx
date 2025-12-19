<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'parent_id',
        'display_order',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(KbCategory::class, 'parent_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'category_id');
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'category_id')
            ->where('status', 'published');
    }

    public function getArticleCount(): int
    {
        return $this->articles()->count();
    }

    public function getPublishedArticleCount(): int
    {
        return $this->publishedArticles()->count();
    }
}
