<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Modules;

use App\Domain\Modules\Entities\Block;
use App\Domain\Modules\Entities\Field;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\BlockRepositoryInterface;
use App\Domain\Modules\Repositories\FieldRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRecordRepositoryInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\BlockType;
use App\Domain\Modules\ValueObjects\FieldType;
use App\Domain\Modules\ValueObjects\ModuleSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleRepositoryInterface $moduleRepository,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly FieldRepositoryInterface $fieldRepository,
        private readonly ModuleRecordRepositoryInterface $recordRepository,
    ) {}

    /**
     * Get all modules.
     */
    public function index(): JsonResponse
    {
        try {
            $modules = $this->moduleRepository->findAll();

            return $this->listResponse($this->serializeModules($modules), 'modules');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to fetch modules', 'ModuleController@index');
        }
    }

    /**
     * Get only active modules.
     */
    public function active(): JsonResponse
    {
        try {
            $modules = $this->moduleRepository->findActive();

            return $this->listResponse($this->serializeModules($modules), 'modules');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to fetch active modules', 'ModuleController@active');
        }
    }

    /**
     * Get record counts for all active modules.
     */
    public function stats(): JsonResponse
    {
        try {
            $modules = $this->moduleRepository->findActive();
            $stats = [];

            foreach ($modules as $module) {
                $count = $this->recordRepository->count($module->getId());
                $stats[] = [
                    'id' => $module->getId(),
                    'name' => $module->getName(),
                    'api_name' => $module->getApiName(),
                    'icon' => $module->getIcon(),
                    'count' => $count,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Failed to fetch module stats', 'ModuleController@stats');
        }
    }

    /**
     * Create a new module.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'singular_name' => 'required|string|max:255',
                'icon' => 'nullable|string|max:100',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'settings' => 'nullable|array',
                'display_order' => 'nullable|integer',
                'default_filters' => 'nullable|array',
                'default_sorting' => 'nullable|array',
                'default_column_visibility' => 'nullable|array',
                'default_page_size' => 'nullable|integer|min:10|max:200',
                'blocks' => 'required|array|min:1',
                'blocks.*.name' => 'required|string',
                'blocks.*.type' => 'required|in:section,tab,accordion,card',
                'blocks.*.display_order' => 'nullable|integer',
                'blocks.*.settings' => 'nullable|array',
                'blocks.*.fields' => 'required|array|min:1',
                'blocks.*.fields.*.label' => 'required|string',
                'blocks.*.fields.*.type' => 'required|string',
                'blocks.*.fields.*.api_name' => 'nullable|string',
                'blocks.*.fields.*.is_required' => 'nullable|boolean',
                'blocks.*.fields.*.width' => 'nullable|integer',
                'blocks.*.fields.*.display_order' => 'nullable|integer',
                'blocks.*.fields.*.options' => 'nullable|array',
            ]);

            // Check if module name already exists
            if ($this->moduleRepository->existsByName($validated['name'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'A module with this name already exists',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Create the module entity
            $settings = ModuleSettings::fromArray(array_merge(
                $validated['settings'] ?? [],
                [
                    'default_filters' => $validated['default_filters'] ?? null,
                    'default_sorting' => $validated['default_sorting'] ?? null,
                    'default_column_visibility' => $validated['default_column_visibility'] ?? null,
                    'default_page_size' => $validated['default_page_size'] ?? 50,
                ]
            ));

            $module = Module::create(
                name: $validated['name'],
                singularName: $validated['singular_name'],
                icon: $validated['icon'] ?? null,
                description: $validated['description'] ?? null,
                settings: $settings,
                displayOrder: $validated['display_order'] ?? 0
            );

            // Save the module
            $savedModule = $this->moduleRepository->save($module);

            // Create blocks and fields
            foreach ($validated['blocks'] ?? [] as $blockIndex => $blockData) {
                $block = Block::create(
                    moduleId: $savedModule->getId(),
                    name: $blockData['name'],
                    type: BlockType::from($blockData['type']),
                    displayOrder: $blockData['display_order'] ?? $blockIndex,
                    settings: $blockData['settings'] ?? []
                );

                $savedBlock = $this->blockRepository->save($block);

                // Create fields for this block
                foreach ($blockData['fields'] ?? [] as $fieldIndex => $fieldData) {
                    $apiName = $fieldData['api_name'] ?? Str::snake($fieldData['label']);

                    $field = Field::create(
                        moduleId: $savedModule->getId(),
                        blockId: $savedBlock->getId(),
                        label: $fieldData['label'],
                        type: FieldType::from($fieldData['type']),
                        isRequired: $fieldData['is_required'] ?? false,
                        displayOrder: $fieldData['display_order'] ?? $fieldIndex,
                    );

                    $this->fieldRepository->save($field);
                }
            }

            // Reload the module with all relations
            $createdModule = $this->moduleRepository->findById($savedModule->getId());

            return response()->json([
                'success' => true,
                'message' => 'Module created successfully',
                'module' => $this->serializeModule($createdModule),
            ], Response::HTTP_CREATED);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create module',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a single module by ID.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $module = $this->moduleRepository->findById($id);

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'module' => $this->serializeModule($module),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch module',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a single module by API name.
     */
    public function showByApiName(string $apiName): JsonResponse
    {
        try {
            $module = $this->moduleRepository->findByApiName($apiName);

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'module' => $this->serializeModule($module),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch module',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a module.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'singular_name' => 'sometimes|string|max:255',
                'icon' => 'nullable|string|max:100',
                'description' => 'nullable|string',
                'settings' => 'nullable|array',
                'display_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
                'default_filters' => 'nullable|array',
                'default_sorting' => 'nullable|array',
                'default_column_visibility' => 'nullable|array',
                'default_page_size' => 'nullable|integer|min:10|max:200',
            ]);

            $module = $this->moduleRepository->findById($id);

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Update module details if provided
            if (isset($validated['name']) || isset($validated['singular_name']) ||
                array_key_exists('icon', $validated) || array_key_exists('description', $validated)) {
                $module = $module->updateDetails(
                    name: $validated['name'] ?? $module->getName(),
                    singularName: $validated['singular_name'] ?? $module->getSingularName(),
                    icon: array_key_exists('icon', $validated) ? $validated['icon'] : $module->getIcon(),
                    description: array_key_exists('description', $validated) ? $validated['description'] : $module->getDescription()
                );
            }

            // Update display order if provided
            if (isset($validated['display_order'])) {
                $module = $module->updateDisplayOrder($validated['display_order']);
            }

            // Update active status if provided
            if (isset($validated['is_active'])) {
                $module = $validated['is_active'] ? $module->activate() : $module->deactivate();
            }

            // Update settings if provided
            if (isset($validated['settings']) || isset($validated['default_filters']) ||
                isset($validated['default_sorting']) || isset($validated['default_column_visibility']) ||
                isset($validated['default_page_size'])) {
                $currentSettings = $module->getSettings()->jsonSerialize();
                $newSettings = ModuleSettings::fromArray(array_merge(
                    $currentSettings,
                    $validated['settings'] ?? [],
                    [
                        'default_filters' => $validated['default_filters'] ?? ($currentSettings['default_filters'] ?? null),
                        'default_sorting' => $validated['default_sorting'] ?? ($currentSettings['default_sorting'] ?? null),
                        'default_column_visibility' => $validated['default_column_visibility'] ?? ($currentSettings['default_column_visibility'] ?? null),
                        'default_page_size' => $validated['default_page_size'] ?? ($currentSettings['default_page_size'] ?? 50),
                    ]
                ));
                $module = $module->updateSettings($newSettings);
            }

            // Save the updated module
            $updatedModule = $this->moduleRepository->save($module);

            return response()->json([
                'success' => true,
                'message' => 'Module updated successfully',
                'module' => $this->serializeModule($updatedModule),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update module',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a module.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $module = $this->moduleRepository->findById($id);

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $this->moduleRepository->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Module deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete module',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reorder modules.
     */
    public function reorder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'modules' => 'required|array',
                'modules.*.id' => 'required|integer',
                'modules.*.display_order' => 'required|integer|min:0',
            ]);

            DB::transaction(function () use ($validated) {
                foreach ($validated['modules'] as $moduleData) {
                    $module = $this->moduleRepository->findById($moduleData['id']);
                    if ($module) {
                        $updatedModule = $module->updateDisplayOrder($moduleData['display_order']);
                        $this->moduleRepository->save($updatedModule);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Modules reordered successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder modules',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Toggle module active status.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $module = $this->moduleRepository->findById($id);

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Toggle the status
            $updatedModule = $module->isActive() ? $module->deactivate() : $module->activate();
            $savedModule = $this->moduleRepository->save($updatedModule);

            return response()->json([
                'success' => true,
                'message' => 'Module status updated successfully',
                'module' => $this->serializeModule($savedModule),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle module status',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Serialize a module entity to array for JSON response.
     */
    private function serializeModule(Module $module): array
    {
        $blocks = [];
        foreach ($module->getBlocks() as $block) {
            $fields = [];
            foreach ($block->fields() as $field) {
                $options = [];
                foreach ($field->options() as $option) {
                    $options[] = [
                        'id' => $option->getId(),
                        'label' => $option->label(),
                        'value' => $option->value(),
                        'color' => $option->color(),
                        'display_order' => $option->displayOrder(),
                        'is_active' => $option->isActive(),
                    ];
                }

                $fields[] = [
                    'id' => $field->getId(),
                    'module_id' => $field->moduleId(),
                    'block_id' => $field->blockId(),
                    'label' => $field->label(),
                    'api_name' => $field->apiName(),
                    'type' => $field->type()->value,
                    'description' => $field->description(),
                    'help_text' => $field->helpText(),
                    'is_required' => $field->isRequired(),
                    'is_unique' => $field->isUnique(),
                    'is_searchable' => $field->isSearchable(),
                    'is_filterable' => $field->isFilterable(),
                    'is_sortable' => $field->isSortable(),
                    'default_value' => $field->defaultValue(),
                    'display_order' => $field->displayOrder(),
                    'width' => $field->width(),
                    'settings' => $field->settings()->jsonSerialize(),
                    'options' => $options,
                ];
            }

            $blocks[] = [
                'id' => $block->getId(),
                'module_id' => $block->moduleId(),
                'name' => $block->name(),
                'type' => $block->type()->value,
                'display_order' => $block->displayOrder(),
                'settings' => $block->settings(),
                'fields' => $fields,
            ];
        }

        // Standalone fields (not in blocks)
        $standaloneFields = [];
        foreach ($module->getFields() as $field) {
            if ($field->blockId() === null) {
                $options = [];
                foreach ($field->options() as $option) {
                    $options[] = [
                        'id' => $option->getId(),
                        'label' => $option->label(),
                        'value' => $option->value(),
                        'color' => $option->color(),
                        'display_order' => $option->displayOrder(),
                        'is_active' => $option->isActive(),
                    ];
                }

                $standaloneFields[] = [
                    'id' => $field->getId(),
                    'module_id' => $field->moduleId(),
                    'block_id' => null,
                    'label' => $field->label(),
                    'api_name' => $field->apiName(),
                    'type' => $field->type()->value,
                    'description' => $field->description(),
                    'help_text' => $field->helpText(),
                    'is_required' => $field->isRequired(),
                    'is_unique' => $field->isUnique(),
                    'is_searchable' => $field->isSearchable(),
                    'is_filterable' => $field->isFilterable(),
                    'is_sortable' => $field->isSortable(),
                    'default_value' => $field->defaultValue(),
                    'display_order' => $field->displayOrder(),
                    'width' => $field->width(),
                    'settings' => $field->settings()->jsonSerialize(),
                    'options' => $options,
                ];
            }
        }

        return [
            'id' => $module->getId(),
            'name' => $module->getName(),
            'singular_name' => $module->getSingularName(),
            'api_name' => $module->getApiName(),
            'icon' => $module->getIcon(),
            'description' => $module->getDescription(),
            'is_active' => $module->isActive(),
            'settings' => $module->getSettings()->jsonSerialize(),
            'display_order' => $module->getDisplayOrder(),
            'created_at' => $module->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $module->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'blocks' => $blocks,
            'fields' => $standaloneFields,
        ];
    }

    /**
     * Serialize multiple module entities.
     *
     * @param array<Module> $modules
     * @return array
     */
    private function serializeModules(array $modules): array
    {
        return array_map(fn (Module $module) => $this->serializeModule($module), $modules);
    }
}
