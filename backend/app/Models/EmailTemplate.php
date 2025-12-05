<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    // Template types
    public const TYPE_USER = 'user';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_WORKFLOW = 'workflow';

    protected $fillable = [
        'name',
        'description',
        'type',
        'module_id',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'attachments',
        'is_active',
        'is_default',
        'category',
        'tags',
        'usage_count',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'variables' => 'array',
        'attachments' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'tags' => 'array',
        'usage_count' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected $attributes = [
        'type' => self::TYPE_USER,
        'is_active' => true,
        'is_default' => false,
        'usage_count' => 0,
        'variables' => '[]',
        'attachments' => '[]',
        'tags' => '[]',
    ];

    /**
     * Get the module this template is for.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for user templates.
     */
    public function scopeUserTemplates($query)
    {
        return $query->where('type', self::TYPE_USER);
    }

    /**
     * Scope for module-specific templates.
     */
    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId)
            ->orWhereNull('module_id');
    }

    /**
     * Scope for searching by category.
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Render the template with given data.
     */
    public function render(array $data): array
    {
        $subject = $this->replaceVariables($this->subject, $data);
        $bodyHtml = $this->replaceVariables($this->body_html, $data);
        $bodyText = $this->body_text
            ? $this->replaceVariables($this->body_text, $data)
            : strip_tags($bodyHtml);

        return [
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Replace variables in content.
     */
    protected function replaceVariables(string $content, array $data): string
    {
        // Handle nested data with dot notation
        $flatData = $this->flattenData($data);

        foreach ($flatData as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                // Support both {{variable}} and {variable} syntax
                $content = str_replace("{{" . $key . "}}", (string) $value, $content);
                $content = str_replace("{" . $key . "}", (string) $value, $content);
            }
        }

        // Remove any remaining variables
        $content = preg_replace('/\{\{[^}]+\}\}/', '', $content);
        $content = preg_replace('/\{[^}]+\}/', '', $content);

        return $content;
    }

    /**
     * Flatten nested array to dot notation.
     */
    protected function flattenData(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenData($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Get available variables for this template.
     */
    public function getAvailableVariables(): array
    {
        $baseVariables = [
            'user.name' => 'Current user name',
            'user.email' => 'Current user email',
            'date.today' => "Today's date",
            'date.now' => 'Current date and time',
            'company.name' => 'Company name',
        ];

        if ($this->module) {
            $moduleVariables = [];
            foreach ($this->module->fields as $field) {
                $moduleVariables['record.' . $field->api_name] = $field->label;
            }
            return array_merge($baseVariables, $moduleVariables);
        }

        return $baseVariables;
    }

    /**
     * Increment usage count.
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Duplicate this template.
     */
    public function duplicate(?int $userId = null): self
    {
        $copy = $this->replicate();
        $copy->name = $this->name . ' (Copy)';
        $copy->is_default = false;
        $copy->usage_count = 0;
        $copy->created_by = $userId ?? $this->created_by;
        $copy->updated_by = $userId;
        $copy->save();

        return $copy;
    }
}
