<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPageVersion extends Model
{
    protected $fillable = [
        'page_id',
        'version_number',
        'title',
        'content',
        'seo_data',
        'change_summary',
        'created_by',
    ];

    protected $casts = [
        'content' => 'array',
        'seo_data' => 'array',
        'version_number' => 'integer',
        'page_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForPage($query, int $pageId)
    {
        return $query->where('page_id', $pageId);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('version_number');
    }

    public function restore(): void
    {
        $this->page->update([
            'title' => $this->title,
            'content' => $this->content,
            'meta_title' => $this->seo_data['meta_title'] ?? null,
            'meta_description' => $this->seo_data['meta_description'] ?? null,
            'meta_keywords' => $this->seo_data['meta_keywords'] ?? null,
        ]);
    }

    public function getContentDiff(CmsPageVersion $other): array
    {
        // Basic diff - could be enhanced with a proper diff library
        return [
            'title_changed' => $this->title !== $other->title,
            'content_changed' => $this->content !== $other->content,
            'seo_changed' => $this->seo_data !== $other->seo_data,
        ];
    }
}
