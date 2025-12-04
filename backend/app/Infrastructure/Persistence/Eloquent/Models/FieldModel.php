<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FieldModel extends Model
{
    use HasFactory;

    protected $table = 'fields';

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
        'display_order' => 'integer',
        'width' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleModel::class, 'module_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(BlockModel::class, 'block_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(FieldOptionModel::class, 'field_id')->orderBy('display_order');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(ModuleRelationshipModel::class, 'field_id', 'field_id');
    }
}
