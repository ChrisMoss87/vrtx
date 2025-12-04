<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Modules\ValueObjects\ConditionalVisibility;
use App\Domain\Modules\ValueObjects\FieldDependency;
use App\Domain\Modules\ValueObjects\FormulaDefinition;
use App\Domain\Modules\ValueObjects\LookupConfiguration;
use App\Domain\Modules\ValueObjects\ValidationRule;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'block_id',
        'label',
        'api_name',
        'type',
        'description',
        'help_text',
        'placeholder',
        'is_required',
        'is_unique',
        'is_searchable',
        'is_filterable',
        'is_sortable',
        'validation_rules',
        'settings',
        'conditional_visibility',
        'field_dependency',
        'formula_definition',
        'lookup_settings',
        'default_value',
        'display_order',
        'width',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'block_id' => 'integer',
        'is_required' => 'boolean',
        'is_unique' => 'boolean',
        'is_searchable' => 'boolean',
        'is_filterable' => 'boolean',
        'is_sortable' => 'boolean',
        'validation_rules' => 'array',
        'settings' => 'array',
        'conditional_visibility' => 'array',
        'field_dependency' => 'array',
        'formula_definition' => 'array',
        'lookup_settings' => 'array',
        'display_order' => 'integer',
        'width' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_required' => false,
        'is_unique' => false,
        'is_searchable' => true,
        'is_filterable' => true,
        'is_sortable' => true,
        'validation_rules' => '[]',
        'settings' => '{}',
        'conditional_visibility' => null,
        'field_dependency' => null,
        'formula_definition' => null,
        'lookup_settings' => null,
        'display_order' => 0,
        'width' => 100,
    ];

    /**
     * Get the module that owns the field.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the block that owns the field.
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    /**
     * Get the options for this field (for select, radio, multiselect types).
     */
    public function options(): HasMany
    {
        return $this->hasMany(FieldOption::class)->orderBy('display_order');
    }

    /**
     * Scope a query to only include required fields.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope a query to only include searchable fields.
     */
    public function scopeSearchable($query)
    {
        return $query->where('is_searchable', true);
    }

    /**
     * Scope a query to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    /**
     * Check if this field type requires options.
     */
    public function requiresOptions(): bool
    {
        return in_array($this->type, ['select', 'multiselect', 'radio']);
    }

    /**
     * Get conditional visibility as value object.
     */
    protected function conditionalVisibilityObject(): Attribute
    {
        return Attribute::make(
            get: fn (): ConditionalVisibility => $this->conditional_visibility
                ? ConditionalVisibility::fromArray($this->conditional_visibility)
                : ConditionalVisibility::disabled(),
        );
    }

    /**
     * Get field dependency as value object.
     */
    protected function fieldDependencyObject(): Attribute
    {
        return Attribute::make(
            get: fn (): FieldDependency => $this->field_dependency
                ? FieldDependency::fromArray($this->field_dependency)
                : FieldDependency::none(),
        );
    }

    /**
     * Get formula definition as value object.
     */
    protected function formulaDefinitionObject(): Attribute
    {
        return Attribute::make(
            get: fn (): FormulaDefinition => $this->formula_definition
                ? FormulaDefinition::fromArray($this->formula_definition)
                : FormulaDefinition::empty(),
        );
    }

    /**
     * Get lookup settings as value object.
     */
    protected function lookupSettingsObject(): Attribute
    {
        return Attribute::make(
            get: fn (): ?LookupConfiguration => $this->lookup_settings
                ? LookupConfiguration::fromArray($this->lookup_settings)
                : null,
        );
    }

    /**
     * Get validation rules as value object.
     */
    protected function validationRulesObject(): Attribute
    {
        return Attribute::make(
            get: fn (): ValidationRule => ValidationRule::fromArray([
                'rules' => $this->validation_rules ?? [],
            ]),
        );
    }

    /**
     * Check if field has conditional visibility enabled.
     */
    public function hasConditionalVisibility(): bool
    {
        return $this->conditionalVisibilityObject->isEnabled();
    }

    /**
     * Check if field is a formula/calculated field.
     */
    public function isFormulaField(): bool
    {
        return $this->type === 'formula' && $this->formulaDefinitionObject->isValid();
    }

    /**
     * Check if field is a lookup/relationship field.
     */
    public function isLookupField(): bool
    {
        return $this->type === 'lookup' && $this->lookupSettingsObject !== null;
    }

    /**
     * Get all field api_names this field depends on.
     *
     * @return array<string>
     */
    public function getDependencies(): array
    {
        $dependencies = [];

        // Add conditional visibility dependencies
        if ($this->hasConditionalVisibility()) {
            $dependencies = array_merge($dependencies, $this->conditionalVisibilityObject->getDependencies());
        }

        // Add formula dependencies
        if ($this->isFormulaField()) {
            $dependencies = array_merge($dependencies, $this->formulaDefinitionObject->dependencies);
        }

        // Add lookup dependencies
        if ($this->isLookupField() && $this->lookupSettingsObject->hasDependency()) {
            $dependencies[] = $this->lookupSettingsObject->dependsOn;
        }

        return array_unique($dependencies);
    }

    /**
     * Evaluate if field should be visible based on form data.
     *
     * @param array<string, mixed> $formData
     */
    public function isVisible(array $formData): bool
    {
        return $this->conditionalVisibilityObject->evaluate($formData);
    }

    /**
     * Get Laravel validation rules for this field.
     *
     * @return array<string>
     */
    public function getValidationRules(): array
    {
        $rules = $this->validationRulesObject->toValidationArray();

        // Add required rule if needed
        if ($this->is_required && !$this->validationRulesObject->isRequired()) {
            array_unshift($rules, 'required');
        }

        // Add unique rule if needed
        if ($this->is_unique && !$this->validationRulesObject->isUnique()) {
            $tableName = 'module_records';
            $rules[] = "unique:{$tableName},data->{$this->api_name}";
        }

        return $rules;
    }
}
