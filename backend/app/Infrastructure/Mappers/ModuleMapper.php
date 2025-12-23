<?php

declare(strict_types=1);

namespace App\Infrastructure\Mappers;

use App\Domain\Modules\DTOs\BlockDefinitionDTO;
use App\Domain\Modules\DTOs\FieldDefinitionDTO;
use App\Domain\Modules\DTOs\FieldOptionDefinitionDTO;
use App\Domain\Modules\DTOs\ModuleDefinitionDTO;
use App\Models\Block;
use App\Models\Field;
use App\Models\FieldOption;
use App\Models\Module;

/**
 * Mapper for converting Eloquent models to Domain DTOs.
 *
 * This class belongs in the Infrastructure layer as it has knowledge
 * of both the Eloquent models and Domain DTOs.
 */
final class ModuleMapper
{
    /**
     * Map a Module model to ModuleDefinitionDTO.
     */
    public static function toModuleDefinitionDTO(Module $module): ModuleDefinitionDTO
    {
        $module->loadMissing(['blocks.fields.options', 'fields.options']);

        $blocks = [];
        foreach ($module->blocks as $block) {
            $blocks[] = self::toBlockDefinitionDTO($block);
        }

        $fields = [];
        foreach ($module->fields as $field) {
            $fields[] = self::toFieldDefinitionDTO($field);
        }

        return new ModuleDefinitionDTO(
            id: $module->id,
            name: $module->name,
            singularName: $module->singular_name,
            apiName: $module->api_name,
            icon: $module->icon,
            description: $module->description,
            isActive: $module->is_active,
            settings: $module->settings,
            displayOrder: $module->display_order,
            blocks: $blocks,
            fields: $fields,
            createdAt: $module->created_at,
            updatedAt: $module->updated_at,
        );
    }

    /**
     * Map a Block model to BlockDefinitionDTO.
     */
    public static function toBlockDefinitionDTO(Block $block): BlockDefinitionDTO
    {
        $block->loadMissing('fields.options');

        $fields = [];
        foreach ($block->fields as $field) {
            $fields[] = self::toFieldDefinitionDTO($field);
        }

        return new BlockDefinitionDTO(
            id: $block->id,
            moduleId: $block->module_id,
            name: $block->name,
            type: $block->type,
            displayOrder: $block->display_order,
            settings: $block->settings,
            fields: $fields,
            createdAt: $block->created_at,
            updatedAt: $block->updated_at,
        );
    }

    /**
     * Map a Field model to FieldDefinitionDTO.
     */
    public static function toFieldDefinitionDTO(Field $field): FieldDefinitionDTO
    {
        $field->loadMissing('options');

        $options = [];
        foreach ($field->options as $option) {
            $options[] = self::toFieldOptionDefinitionDTO($option);
        }

        return new FieldDefinitionDTO(
            id: $field->id,
            moduleId: $field->module_id,
            blockId: $field->block_id,
            label: $field->label,
            apiName: $field->api_name,
            type: $field->type,
            description: $field->description,
            helpText: $field->help_text,
            placeholder: $field->placeholder,
            isRequired: $field->is_required,
            isUnique: $field->is_unique,
            isSearchable: $field->is_searchable,
            isFilterable: $field->is_filterable,
            isSortable: $field->is_sortable,
            validationRules: $field->validation_rules,
            settings: $field->settings,
            conditionalVisibility: $field->conditional_visibility,
            fieldDependency: $field->field_dependency,
            formulaDefinition: $field->formula_definition,
            lookupSettings: $field->lookup_settings,
            defaultValue: $field->default_value,
            displayOrder: $field->display_order,
            width: $field->width,
            options: $options,
            createdAt: $field->created_at,
            updatedAt: $field->updated_at,
        );
    }

    /**
     * Map a FieldOption model to FieldOptionDefinitionDTO.
     */
    public static function toFieldOptionDefinitionDTO(FieldOption $option): FieldOptionDefinitionDTO
    {
        return new FieldOptionDefinitionDTO(
            id: $option->id,
            fieldId: $option->field_id,
            label: $option->label,
            value: $option->value,
            color: $option->color,
            isActive: $option->is_active,
            displayOrder: $option->display_order,
            metadata: $option->metadata,
            createdAt: $option->created_at,
            updatedAt: $option->updated_at,
        );
    }
}
