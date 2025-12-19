<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WizardStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'wizard_id',
        'title',
        'description',
        'type',
        'fields',
        'can_skip',
        'display_order',
        'conditional_logic',
        'validation_rules',
    ];

    protected $casts = [
        'fields' => 'array',
        'can_skip' => 'boolean',
        'display_order' => 'integer',
        'conditional_logic' => 'array',
        'validation_rules' => 'array',
    ];

    public function wizard(): BelongsTo
    {
        return $this->belongsTo(Wizard::class);
    }

    public function getFieldCountAttribute(): int
    {
        return count($this->fields ?? []);
    }

    public function hasConditionalLogic(): bool
    {
        $logic = $this->conditional_logic;
        return !empty($logic) && ($logic['enabled'] ?? false);
    }

    public function shouldSkip(array $formData): bool
    {
        if (!$this->hasConditionalLogic()) {
            return false;
        }

        $conditions = $this->conditional_logic['skipIf'] ?? [];

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '==';
            $value = $condition['value'] ?? null;

            if (!$field) {
                continue;
            }

            $fieldValue = $formData[$field] ?? null;

            $matches = match ($operator) {
                '==' => $fieldValue == $value,
                '!=' => $fieldValue != $value,
                '>' => $fieldValue > $value,
                '<' => $fieldValue < $value,
                '>=' => $fieldValue >= $value,
                '<=' => $fieldValue <= $value,
                'contains' => str_contains((string) $fieldValue, (string) $value),
                'not_contains' => !str_contains((string) $fieldValue, (string) $value),
                'empty' => empty($fieldValue),
                'not_empty' => !empty($fieldValue),
                default => false,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }
}
