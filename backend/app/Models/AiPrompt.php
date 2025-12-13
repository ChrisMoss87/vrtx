<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPrompt extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'category',
        'system_prompt',
        'user_prompt_template',
        'variables',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Categories
    public const CATEGORY_EMAIL = 'email';
    public const CATEGORY_SCORING = 'scoring';
    public const CATEGORY_SENTIMENT = 'sentiment';
    public const CATEGORY_SUMMARY = 'summary';
    public const CATEGORY_PREDICTION = 'prediction';

    /**
     * Render the user prompt with variables
     */
    public function renderUserPrompt(array $variables): string
    {
        $prompt = $this->user_prompt_template;

        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }
            $prompt = str_replace('{{' . $key . '}}', (string) $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Get messages array for LLM
     */
    public function getMessages(array $variables): array
    {
        return [
            ['role' => 'system', 'content' => $this->system_prompt],
            ['role' => 'user', 'content' => $this->renderUserPrompt($variables)],
        ];
    }

    /**
     * Scope active prompts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Find by slug
     */
    public static function findBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->active()->first();
    }
}
