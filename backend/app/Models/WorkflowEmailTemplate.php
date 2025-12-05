<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Email templates for workflow email actions.
 */
class WorkflowEmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'subject',
        'body_html',
        'body_text',
        'from_name',
        'from_email',
        'reply_to',
        'available_variables',
        'category',
        'is_system',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'available_variables' => 'array',
        'is_system' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected $attributes = [
        'is_system' => false,
    ];

    /**
     * Get the user who created this template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this template.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to filter by category.
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get only system templates.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to get only user-created templates.
     */
    public function scopeUserCreated($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Render the subject with variable substitution.
     */
    public function renderSubject(array $variables): string
    {
        return $this->substituteVariables($this->subject, $variables);
    }

    /**
     * Render the HTML body with variable substitution.
     */
    public function renderBodyHtml(array $variables): string
    {
        return $this->substituteVariables($this->body_html, $variables);
    }

    /**
     * Render the text body with variable substitution.
     */
    public function renderBodyText(array $variables): ?string
    {
        if (!$this->body_text) {
            return null;
        }

        return $this->substituteVariables($this->body_text, $variables);
    }

    /**
     * Substitute variables in a template string.
     * Variables are in the format {{variable_name}} or {{record.field_name}}.
     */
    protected function substituteVariables(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            function ($matches) use ($variables) {
                $key = trim($matches[1]);

                // Handle nested keys (e.g., record.name, user.email)
                $value = $this->getNestedValue($variables, $key);

                if ($value === null) {
                    return $matches[0]; // Keep original if not found
                }

                if (is_array($value) || is_object($value)) {
                    return json_encode($value);
                }

                return (string) $value;
            },
            $template
        );
    }

    /**
     * Get a nested value from an array using dot notation.
     */
    protected function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } elseif (is_object($value) && property_exists($value, $key)) {
                $value = $value->{$key};
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Get default available variables for workflows.
     */
    public static function getDefaultVariables(): array
    {
        return [
            'record' => [
                'description' => 'The triggering record',
                'fields' => ['id', 'name', 'created_at', 'updated_at', '...module fields'],
            ],
            'user' => [
                'description' => 'The user who triggered the workflow',
                'fields' => ['id', 'name', 'email'],
            ],
            'current_user' => [
                'description' => 'The currently logged in user',
                'fields' => ['id', 'name', 'email'],
            ],
            'trigger' => [
                'description' => 'Information about the trigger event',
                'fields' => ['type', 'changed_fields', 'old_values', 'new_values'],
            ],
            'now' => [
                'description' => 'Current date/time',
                'fields' => ['date', 'time', 'datetime', 'timestamp'],
            ],
        ];
    }
}
