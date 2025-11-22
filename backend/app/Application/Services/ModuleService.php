<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Modules\Entities\Block;
use App\Domain\Modules\Entities\Field;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\BlockRepositoryInterface;
use App\Domain\Modules\Repositories\FieldRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\BlockType;
use App\Domain\Modules\ValueObjects\FieldType;
use App\Domain\Modules\ValueObjects\ModuleSettings;
use Exception;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ModuleService
{
    public function __construct(
        private readonly ModuleRepositoryInterface $moduleRepository,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly FieldRepositoryInterface $fieldRepository,
    ) {}

    /**
     * Create a new module with blocks and fields.
     *
     * @param  array{name: string, singular_name: string, icon?: string, description?: string, is_active?: bool, settings?: array, blocks?: array}  $data
     *
     * @throws RuntimeException If module creation fails
     */
    public function createModule(array $data): Module
    {
        // Validate module name uniqueness
        if ($this->moduleRepository->existsByName($data['name'])) {
            throw new RuntimeException("Module with name '{$data['name']}' already exists.");
        }

        DB::beginTransaction();

        try {
            // Create module entity
            $module = Module::create(
                name: $data['name'],
                singularName: $data['singular_name'],
                icon: $data['icon'] ?? 'database',
                description: $data['description'] ?? null,
                settings: isset($data['settings']) ? ModuleSettings::fromArray($data['settings']) : null,
                displayOrder: $data['display_order'] ?? 0
            );

            // Persist module
            $module = $this->moduleRepository->save($module);

            // Create blocks if provided
            if (!empty($data['blocks'])) {
                foreach ($data['blocks'] as $blockData) {
                    $this->createBlockForModule($module->id(), $blockData);
                }
            }

            DB::commit();

            return $this->moduleRepository->findById($module->id());
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException("Failed to create module: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Update an existing module.
     *
     * @throws RuntimeException If module update fails
     */
    public function updateModule(int $moduleId, array $data): Module
    {
        DB::beginTransaction();

        try {
            $module = $this->moduleRepository->findById($moduleId);

            if (!$module) {
                throw new RuntimeException("Module not found with ID {$moduleId}.");
            }

            // Validate name if being changed
            if (isset($data['name']) && $data['name'] !== $module->name()) {
                if ($this->moduleRepository->existsByName($data['name'], $moduleId)) {
                    throw new RuntimeException("Module with name '{$data['name']}' already exists.");
                }
            }

            // Update module details
            $module->updateDetails(
                name: $data['name'] ?? $module->name(),
                singularName: $data['singular_name'] ?? $module->singularName(),
                icon: $data['icon'] ?? $module->icon(),
                description: $data['description'] ?? $module->description()
            );

            if (isset($data['settings'])) {
                $module->updateSettings(ModuleSettings::fromArray($data['settings']));
            }

            if (isset($data['display_order'])) {
                $module->updateDisplayOrder($data['display_order']);
            }

            $module = $this->moduleRepository->save($module);

            DB::commit();

            return $this->moduleRepository->findById($module->id());
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException("Failed to update module: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete a module and all associated data.
     *
     * @throws RuntimeException If module deletion fails
     */
    public function deleteModule(int $moduleId): void
    {
        DB::beginTransaction();

        try {
            if (!$this->moduleRepository->delete($moduleId)) {
                throw new RuntimeException("Module not found with ID {$moduleId}.");
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException("Failed to delete module: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create a block within a module.
     *
     * @param  array{name: string, type: string, display_order?: int, settings?: array, fields?: array}  $data
     *
     * @throws RuntimeException If block creation fails
     */
    public function createBlockForModule(int $moduleId, array $data): Block
    {
        // Validate module exists
        $module = $this->moduleRepository->findById($moduleId);

        if (!$module) {
            throw new RuntimeException("Module not found with ID {$moduleId}.");
        }

        DB::beginTransaction();

        try {
            $block = Block::create(
                moduleId: $moduleId,
                name: $data['name'],
                type: BlockType::from($data['type'] ?? 'section'),
                displayOrder: $data['display_order'] ?? 0,
                settings: $data['settings'] ?? []
            );

            $block = $this->blockRepository->save($block);

            // Create fields if provided
            if (!empty($data['fields'])) {
                foreach ($data['fields'] as $fieldData) {
                    $this->createFieldForBlock($moduleId, $block->id(), $fieldData);
                }
            }

            DB::commit();

            return $this->blockRepository->findById($block->id());
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException("Failed to create block: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create a field within a block.
     *
     * @param  array{label: string, type: string, description?: string, help_text?: string, is_required?: bool, is_unique?: bool, validation_rules?: array, settings?: array, default_value?: string, display_order?: int, width?: int}  $data
     *
     * @throws RuntimeException If field creation fails
     */
    public function createFieldForBlock(int $moduleId, ?int $blockId, array $data): Field
    {
        DB::beginTransaction();

        try {
            // Validate field uniqueness
            if ($this->fieldRepository->existsByApiName($moduleId, $data['api_name'] ?? str_replace(' ', '_', strtolower($data['label'])))) {
                throw new RuntimeException("Field with this API name already exists in this module.");
            }

            $field = Field::create(
                moduleId: $moduleId,
                blockId: $blockId,
                label: $data['label'],
                type: FieldType::from($data['type']),
                description: $data['description'] ?? null,
                helpText: $data['help_text'] ?? null,
                isRequired: $data['is_required'] ?? false,
                isUnique: $data['is_unique'] ?? false,
                isSearchable: $data['is_searchable'] ?? true,
                isFilterable: $data['is_filterable'] ?? true,
                isSortable: $data['is_sortable'] ?? true,
                validationRules: null,
                settings: null,
                defaultValue: $data['default_value'] ?? null,
                displayOrder: $data['display_order'] ?? 0,
                width: $data['width'] ?? 100
            );

            $field = $this->fieldRepository->save($field);

            DB::commit();

            return $field;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException("Failed to create field: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get all active modules with their structure.
     */
    public function getActiveModules(): array
    {
        return $this->moduleRepository->findActive();
    }

    /**
     * Get all modules.
     */
    public function getAllModules(): array
    {
        return $this->moduleRepository->findAll();
    }

    /**
     * Get a single module with full structure.
     */
    public function getModule(int $moduleId): ?Module
    {
        return $this->moduleRepository->findById($moduleId);
    }

    /**
     * Get module by API name.
     */
    public function getModuleByApiName(string $apiName): ?Module
    {
        return $this->moduleRepository->findByApiName($apiName);
    }

    /**
     * Toggle module active status.
     */
    public function toggleModuleStatus(int $moduleId): Module
    {
        DB::beginTransaction();

        try {
            $module = $this->moduleRepository->findById($moduleId);

            if (!$module) {
                throw new RuntimeException("Module not found with ID {$moduleId}.");
            }

            if ($module->isActive()) {
                $module->deactivate();
            } else {
                $module->activate();
            }

            $module = $this->moduleRepository->save($module);

            DB::commit();

            return $module;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException("Failed to toggle module status: {$e->getMessage()}", 0, $e);
        }
    }
}
