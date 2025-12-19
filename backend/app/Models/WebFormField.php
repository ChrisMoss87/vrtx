<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebFormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'web_form_id',
        'field_type',
        'label',
        'name',
        'placeholder',
        'is_required',
        'module_field_id',
        'options',
        'validation_rules',
        'display_order',
        'settings',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'options' => 'array',
        'validation_rules' => 'array',
        'settings' => 'array',
        'display_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_required' => false,
        'display_order' => 0,
        'settings' => '{}',
    ];

    /**
     * Available field types for web forms.
     */
    public const FIELD_TYPES = [
        'text' => 'Text Input',
        'email' => 'Email',
        'phone' => 'Phone',
        'textarea' => 'Text Area',
        'select' => 'Select Dropdown',
        'multi_select' => 'Multi-Select',
        'checkbox' => 'Checkbox',
        'radio' => 'Radio Buttons',
        'date' => 'Date Picker',
        'datetime' => 'Date & Time',
        'number' => 'Number',
        'currency' => 'Currency',
        'file' => 'File Upload',
        'hidden' => 'Hidden Field',
        'url' => 'URL',
    ];

    /**
     * Get the form this field belongs to.
     */
    public function webForm(): BelongsTo
    {
        return $this->belongsTo(WebForm::class);
    }

    /**
     * Get the module field this maps to.
     */
    public function moduleField(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'module_field_id');
    }

    /**
     * Get the field name (or generate from label).
     */
    public function getFieldNameAttribute(): string
    {
        return $this->name ?? \Illuminate\Support\Str::snake($this->label);
    }

    /**
     * Check if this field has options (select, radio, etc).
     */
    public function hasOptions(): bool
    {
        return in_array($this->field_type, ['select', 'multi_select', 'radio', 'checkbox']);
    }

    /**
     * Get options as array.
     */
    public function getOptionsArray(): array
    {
        if (!$this->hasOptions() || empty($this->options)) {
            return [];
        }

        return $this->options;
    }

    /**
     * Get validation rules for this field.
     */
    public function getValidationRulesArray(): array
    {
        $rules = [];

        if ($this->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Type-specific rules
        switch ($this->field_type) {
            case 'email':
                $rules[] = 'email';
                break;
            case 'url':
                $rules[] = 'url';
                break;
            case 'number':
            case 'currency':
                $rules[] = 'numeric';
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'datetime':
                $rules[] = 'date';
                break;
            case 'file':
                $rules[] = 'file';
                $maxSize = $this->validation_rules['max_size'] ?? 10240; // 10MB default
                $rules[] = "max:{$maxSize}";
                if (!empty($this->validation_rules['allowed_types'])) {
                    $rules[] = 'mimes:' . implode(',', $this->validation_rules['allowed_types']);
                }
                break;
            case 'select':
            case 'radio':
                if (!empty($this->options)) {
                    $values = array_column($this->options, 'value');
                    $rules[] = 'in:' . implode(',', $values);
                }
                break;
            case 'multi_select':
                $rules[] = 'array';
                break;
        }

        // Custom validation rules
        if (!empty($this->validation_rules)) {
            if (!empty($this->validation_rules['min_length'])) {
                $rules[] = 'min:' . $this->validation_rules['min_length'];
            }
            if (!empty($this->validation_rules['max_length'])) {
                $rules[] = 'max:' . $this->validation_rules['max_length'];
            }
            if (!empty($this->validation_rules['pattern'])) {
                $rules[] = 'regex:' . $this->validation_rules['pattern'];
            }
        }

        return $rules;
    }

    /**
     * Get setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Scope to order fields.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }
}
