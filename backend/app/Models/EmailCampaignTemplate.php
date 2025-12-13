<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class EmailCampaignTemplate extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'name',
        'description',
        'category',
        'subject',
        'html_content',
        'text_content',
        'thumbnail_url',
        'variables',
        'is_system',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'variables' => '[]',
        'is_system' => false,
        'is_active' => true,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeUserTemplates($query)
    {
        return $query->where('is_system', false);
    }

    public function scopeSystemTemplates($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Render template with variables
     */
    public function render(array $variables): string
    {
        $content = $this->html_content;

        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", (string) $value, $content);
            $content = str_replace("{{ " . $key . " }}", (string) $value, $content);
        }

        return $content;
    }

    /**
     * Get available variables from template content
     */
    public function extractVariables(): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_\.]*)\s*\}\}/', $this->html_content, $matches);
        return array_unique($matches[1] ?? []);
    }
}
