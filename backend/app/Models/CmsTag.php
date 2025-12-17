<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CmsTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(CmsPage::class, 'cms_page_tag', 'tag_id', 'page_id')
            ->withTimestamps();
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where('name', 'like', "%{$search}%");
    }

    public function scopePopular($query, int $limit = 10)
    {
        return $query->withCount('pages')
            ->orderByDesc('pages_count')
            ->limit($limit);
    }

    public function getPageCount(): int
    {
        return $this->pages()->count();
    }

    public static function findOrCreateByName(string $name): self
    {
        $slug = \Illuminate\Support\Str::slug($name);

        return self::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name]
        );
    }
}
