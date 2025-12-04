<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Modules;

use App\Domain\Modules\DTOs\CreateBlockDTO;
use App\Domain\Modules\DTOs\CreateFieldDTO;
use App\Domain\Modules\DTOs\CreateModuleDTO;
use App\Domain\Modules\DTOs\UpdateModuleDTO;
use App\Domain\Modules\Repositories\Interfaces\ModuleRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleRepositoryInterface $moduleRepository
    ) {}

    /**
     * Get all modules.
     */
    public function index(): JsonResponse
    {
        try {
            $modules = $this->moduleRepository->all();

            return response()->json([
                'success' => true,
                'modules' => $modules->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch modules',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get only active modules.
     */
    public function active(): JsonResponse
    {
        try {
            $modules = $this->moduleRepository->all(activeOnly: true);

            return response()->json([
                'success' => true,
                'modules' => $modules->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active modules',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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

            // Convert blocks array to CreateBlockDTO array and collect all fields
            $blocks = [];
            $allFields = [];

            foreach ($validated['blocks'] ?? [] as $blockIndex => $blockData) {
                $blockData['display_order'] = $blockData['display_order'] ?? $blockIndex;
                $block = CreateBlockDTO::fromArray($blockData);
                $blocks[] = $block;

                // Convert fields for this block and add to all fields list
                foreach ($blockData['fields'] ?? [] as $fieldIndex => $fieldData) {
                    // Auto-generate api_name if not provided
                    if (empty($fieldData['api_name'])) {
                        $fieldData['api_name'] = Str::snake($fieldData['label']);
                    }

                    $fieldData['display_order'] = $fieldData['display_order'] ?? $fieldIndex;
                    $fieldData['blockApiName'] = $blockData['name']; // Use block name to associate field with block

                    $allFields[] = CreateFieldDTO::fromArray($fieldData);
                }
            }

            // Build the module DTO
            $moduleData = [
                'name' => $validated['name'],
                'singularName' => $validated['singular_name'],
                'icon' => $validated['icon'] ?? null,
                'description' => $validated['description'] ?? null,
                'isActive' => $validated['is_active'] ?? true,
                'settings' => $validated['settings'] ?? [],
                'displayOrder' => $validated['display_order'] ?? 0,
                'blocks' => $blocks,
                'fields' => $allFields, // All fields with blockApiName set
            ];

            $dto = CreateModuleDTO::fromArray($moduleData);
            $module = $this->moduleRepository->create($dto);

            // Update default view settings if provided
            if (isset($validated['default_filters']) || isset($validated['default_sorting']) ||
                isset($validated['default_column_visibility']) || isset($validated['default_page_size'])) {
                $module->update([
                    'default_filters' => $validated['default_filters'] ?? null,
                    'default_sorting' => $validated['default_sorting'] ?? null,
                    'default_column_visibility' => $validated['default_column_visibility'] ?? null,
                    'default_page_size' => $validated['default_page_size'] ?? 50,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Module created successfully',
                'module' => $module->load(['blocks.fields.options', 'fields.options']),
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
                'module' => $module->load(['blocks.fields.options', 'fields.options']),
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
            $module = Module::where('api_name', $apiName)->first();

            if (!$module) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'module' => $module->load(['blocks.fields.options', 'fields.options']),
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
                // Full blocks and fields update support
                'blocks' => 'nullable|array',
                'blocks.*.id' => 'nullable|integer',
                'blocks.*.name' => 'required_with:blocks|string',
                'blocks.*.type' => 'required_with:blocks|in:section,tab,accordion,card',
                'blocks.*.display_order' => 'nullable|integer',
                'blocks.*.settings' => 'nullable|array',
                'blocks.*.fields' => 'nullable|array',
                'blocks.*.fields.*.id' => 'nullable|integer',
                'blocks.*.fields.*.label' => 'required_with:blocks.*.fields|string',
                'blocks.*.fields.*.type' => 'required_with:blocks.*.fields|string',
                'blocks.*.fields.*.api_name' => 'nullable|string',
                'blocks.*.fields.*.is_required' => 'nullable|boolean',
                'blocks.*.fields.*.is_unique' => 'nullable|boolean',
                'blocks.*.fields.*.is_searchable' => 'nullable|boolean',
                'blocks.*.fields.*.is_filterable' => 'nullable|boolean',
                'blocks.*.fields.*.is_sortable' => 'nullable|boolean',
                'blocks.*.fields.*.description' => 'nullable|string',
                'blocks.*.fields.*.help_text' => 'nullable|string',
                'blocks.*.fields.*.placeholder' => 'nullable|string',
                'blocks.*.fields.*.default_value' => 'nullable|string',
                'blocks.*.fields.*.display_order' => 'nullable|integer',
                'blocks.*.fields.*.width' => 'nullable|integer',
                'blocks.*.fields.*.validation_rules' => 'nullable|array',
                'blocks.*.fields.*.settings' => 'nullable|array',
                'blocks.*.fields.*.conditional_visibility' => 'nullable|array',
                'blocks.*.fields.*.field_dependency' => 'nullable|array',
                'blocks.*.fields.*.formula_definition' => 'nullable|array',
                'blocks.*.fields.*.options' => 'nullable|array',
                'blocks.*.fields.*.options.*.id' => 'nullable|integer',
                'blocks.*.fields.*.options.*.label' => 'required_with:blocks.*.fields.*.options|string',
                'blocks.*.fields.*.options.*.value' => 'required_with:blocks.*.fields.*.options|string',
                'blocks.*.fields.*.options.*.color' => 'nullable|string',
                'blocks.*.fields.*.options.*.display_order' => 'nullable|integer',
            ]);

            $module = Module::findOrFail($id);

            // Use database transaction for atomic updates
            \DB::transaction(function () use ($module, $validated) {
                // Update basic module fields
                if (isset($validated['name'])) {
                    $module->name = $validated['name'];
                }
                if (isset($validated['singular_name'])) {
                    $module->singular_name = $validated['singular_name'];
                }
                if (array_key_exists('icon', $validated)) {
                    $module->icon = $validated['icon'];
                }
                if (array_key_exists('description', $validated)) {
                    $module->description = $validated['description'];
                }
                if (isset($validated['settings'])) {
                    $module->settings = $validated['settings'];
                }
                if (isset($validated['display_order'])) {
                    $module->display_order = $validated['display_order'];
                }
                if (isset($validated['is_active'])) {
                    $module->is_active = $validated['is_active'];
                }

                // Update default view settings
                if (array_key_exists('default_filters', $validated)) {
                    $module->default_filters = $validated['default_filters'];
                }
                if (array_key_exists('default_sorting', $validated)) {
                    $module->default_sorting = $validated['default_sorting'];
                }
                if (array_key_exists('default_column_visibility', $validated)) {
                    $module->default_column_visibility = $validated['default_column_visibility'];
                }
                if (isset($validated['default_page_size'])) {
                    $module->default_page_size = $validated['default_page_size'];
                }

                $module->save();

                // Handle blocks and fields update if provided
                if (isset($validated['blocks'])) {
                    $this->syncBlocks($module, $validated['blocks']);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Module updated successfully',
                'module' => $module->fresh()->load(['blocks.fields.options', 'fields.options']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], Response::HTTP_NOT_FOUND);
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
     * Sync blocks for a module (create, update, delete).
     */
    private function syncBlocks(Module $module, array $blocksData): void
    {
        $existingBlockIds = $module->blocks->pluck('id')->toArray();
        $incomingBlockIds = [];

        foreach ($blocksData as $index => $blockData) {
            $blockData['display_order'] = $blockData['display_order'] ?? $index;

            if (!empty($blockData['id'])) {
                // Update existing block
                $block = \App\Models\Block::find($blockData['id']);
                if ($block && $block->module_id === $module->id) {
                    $block->update([
                        'name' => $blockData['name'],
                        'type' => $blockData['type'],
                        'display_order' => $blockData['display_order'],
                        'settings' => $blockData['settings'] ?? [],
                    ]);
                    $incomingBlockIds[] = $block->id;

                    // Sync fields for this block
                    if (isset($blockData['fields'])) {
                        $this->syncFields($module, $block, $blockData['fields']);
                    }
                }
            } else {
                // Create new block
                $block = \App\Models\Block::create([
                    'module_id' => $module->id,
                    'name' => $blockData['name'],
                    'type' => $blockData['type'],
                    'display_order' => $blockData['display_order'],
                    'settings' => $blockData['settings'] ?? [],
                ]);
                $incomingBlockIds[] = $block->id;

                // Create fields for this block
                if (isset($blockData['fields'])) {
                    $this->syncFields($module, $block, $blockData['fields']);
                }
            }
        }

        // Delete blocks that were removed (along with their fields)
        $blocksToDelete = array_diff($existingBlockIds, $incomingBlockIds);
        if (!empty($blocksToDelete)) {
            // Delete fields in these blocks first
            \App\Models\Field::whereIn('block_id', $blocksToDelete)->delete();
            // Delete the blocks
            \App\Models\Block::whereIn('id', $blocksToDelete)->delete();
        }
    }

    /**
     * Sync fields for a block (create, update, delete).
     */
    private function syncFields(Module $module, \App\Models\Block $block, array $fieldsData): void
    {
        $existingFieldIds = $block->fields->pluck('id')->toArray();
        $incomingFieldIds = [];

        foreach ($fieldsData as $index => $fieldData) {
            $fieldData['display_order'] = $fieldData['display_order'] ?? $index;

            // Auto-generate api_name if not provided
            if (empty($fieldData['api_name'])) {
                $fieldData['api_name'] = Str::snake($fieldData['label']);
            }

            $fieldPayload = [
                'module_id' => $module->id,
                'block_id' => $block->id,
                'label' => $fieldData['label'],
                'api_name' => $fieldData['api_name'],
                'type' => $fieldData['type'],
                'description' => $fieldData['description'] ?? null,
                'help_text' => $fieldData['help_text'] ?? null,
                'placeholder' => $fieldData['placeholder'] ?? null,
                'is_required' => $fieldData['is_required'] ?? false,
                'is_unique' => $fieldData['is_unique'] ?? false,
                'is_searchable' => $fieldData['is_searchable'] ?? true,
                'is_filterable' => $fieldData['is_filterable'] ?? true,
                'is_sortable' => $fieldData['is_sortable'] ?? true,
                'default_value' => $fieldData['default_value'] ?? null,
                'display_order' => $fieldData['display_order'],
                'width' => $fieldData['width'] ?? 100,
                'validation_rules' => $fieldData['validation_rules'] ?? [],
                'settings' => $fieldData['settings'] ?? [],
                'conditional_visibility' => $fieldData['conditional_visibility'] ?? null,
                'field_dependency' => $fieldData['field_dependency'] ?? null,
                'formula_definition' => $fieldData['formula_definition'] ?? null,
            ];

            if (!empty($fieldData['id'])) {
                // Update existing field
                $field = \App\Models\Field::find($fieldData['id']);
                if ($field && $field->module_id === $module->id) {
                    $field->update($fieldPayload);
                    $incomingFieldIds[] = $field->id;

                    // Sync options for this field
                    if (isset($fieldData['options'])) {
                        $this->syncFieldOptions($field, $fieldData['options']);
                    }
                }
            } else {
                // Create new field
                $field = \App\Models\Field::create($fieldPayload);
                $incomingFieldIds[] = $field->id;

                // Create options for this field
                if (isset($fieldData['options'])) {
                    $this->syncFieldOptions($field, $fieldData['options']);
                }
            }
        }

        // Delete fields that were removed
        $fieldsToDelete = array_diff($existingFieldIds, $incomingFieldIds);
        if (!empty($fieldsToDelete)) {
            // Delete options first
            \App\Models\FieldOption::whereIn('field_id', $fieldsToDelete)->delete();
            // Delete the fields
            \App\Models\Field::whereIn('id', $fieldsToDelete)->delete();
        }
    }

    /**
     * Sync options for a field (create, update, delete).
     */
    private function syncFieldOptions(\App\Models\Field $field, array $optionsData): void
    {
        $existingOptionIds = $field->options->pluck('id')->toArray();
        $incomingOptionIds = [];

        foreach ($optionsData as $index => $optionData) {
            $optionPayload = [
                'field_id' => $field->id,
                'label' => $optionData['label'],
                'value' => $optionData['value'],
                'color' => $optionData['color'] ?? null,
                'display_order' => $optionData['display_order'] ?? $index,
                'is_active' => $optionData['is_active'] ?? true,
            ];

            if (!empty($optionData['id'])) {
                // Update existing option
                $option = \App\Models\FieldOption::find($optionData['id']);
                if ($option && $option->field_id === $field->id) {
                    $option->update($optionPayload);
                    $incomingOptionIds[] = $option->id;
                }
            } else {
                // Create new option
                $option = \App\Models\FieldOption::create($optionPayload);
                $incomingOptionIds[] = $option->id;
            }
        }

        // Delete options that were removed
        $optionsToDelete = array_diff($existingOptionIds, $incomingOptionIds);
        if (!empty($optionsToDelete)) {
            \App\Models\FieldOption::whereIn('id', $optionsToDelete)->delete();
        }
    }

    /**
     * Delete a module.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->moduleRepository->delete($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found',
                ], Response::HTTP_NOT_FOUND);
            }

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
            $module->is_active = !$module->is_active;
            $module->save();

            return response()->json([
                'success' => true,
                'message' => 'Module status updated successfully',
                'module' => $module->load(['blocks.fields.options', 'fields.options']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle module status',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
