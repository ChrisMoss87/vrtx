<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkflowTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'icon',
        'workflow_data',
        'required_modules',
        'required_fields',
        'variable_mappings',
        'is_system',
        'is_active',
        'usage_count',
        'difficulty',
        'estimated_time_saved_hours',
    ];

    protected $casts = [
        'workflow_data' => 'array',
        'required_modules' => 'array',
        'required_fields' => 'array',
        'variable_mappings' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'estimated_time_saved_hours' => 'integer',
    ];

    // Categories
    public const CATEGORY_LEAD = 'lead';
    public const CATEGORY_DEAL = 'deal';
    public const CATEGORY_CUSTOMER = 'customer';
    public const CATEGORY_DATA = 'data';
    public const CATEGORY_PRODUCTIVITY = 'productivity';
    public const CATEGORY_COMMUNICATION = 'communication';

    // Difficulty levels
    public const DIFFICULTY_BEGINNER = 'beginner';
    public const DIFFICULTY_INTERMEDIATE = 'intermediate';
    public const DIFFICULTY_ADVANCED = 'advanced';

    /**
     * Get all categories.
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_LEAD => 'Lead Management',
            self::CATEGORY_DEAL => 'Deal & Sales',
            self::CATEGORY_CUSTOMER => 'Customer Success',
            self::CATEGORY_DATA => 'Data Quality',
            self::CATEGORY_PRODUCTIVITY => 'Team Productivity',
            self::CATEGORY_COMMUNICATION => 'Communication',
        ];
    }

    /**
     * Get all difficulty levels.
     */
    public static function getDifficultyLevels(): array
    {
        return [
            self::DIFFICULTY_BEGINNER => 'Beginner',
            self::DIFFICULTY_INTERMEDIATE => 'Intermediate',
            self::DIFFICULTY_ADVANCED => 'Advanced',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (WorkflowTemplate $template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Scope to active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to system templates.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by difficulty.
     */
    public function scopeDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    /**
     * Check if template can be used with given modules.
     */
    public function canUseWithModules(array $availableModuleApiNames): bool
    {
        $required = $this->required_modules ?? [];
        if (empty($required)) {
            return true;
        }

        foreach ($required as $requiredModule) {
            if (!in_array($requiredModule, $availableModuleApiNames)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the workflow data with variable mappings applied.
     */
    public function getWorkflowDataWithMappings(array $mappings): array
    {
        $data = $this->workflow_data;

        // Replace variable placeholders with actual values
        $json = json_encode($data);
        foreach ($mappings as $key => $value) {
            $json = str_replace("{{$key}}", (string) $value, $json);
        }

        return json_decode($json, true);
    }
}
