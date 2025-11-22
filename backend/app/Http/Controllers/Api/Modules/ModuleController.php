<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Modules;

use App\Application\Services\ModuleService;
use App\Domain\Modules\Entities\Module;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleService $moduleService
    ) {}

    /**
     * List all modules.
     */
    public function index(): JsonResponse
    {
        $modules = $this->moduleService->getAllModules();

        return response()->json([
            'modules' => array_map(fn (Module $module) => $this->transformModule($module), $modules),
        ]);
    }

    /**
     * Get active modules only.
     */
    public function active(): JsonResponse
    {
        $modules = $this->moduleService->getActiveModules();

        return response()->json([
            'modules' => array_map(fn (Module $module) => $this->transformModule($module), $modules),
        ]);
    }

    /**
     * Get a single module with full structure (blocks and fields).
     */
    public function show(int $id): JsonResponse
    {
        $module = $this->moduleService->getModule($id);

        if (!$module) {
            return response()->json(['error' => 'Module not found'], 404);
        }

        return response()->json([
            'module' => $this->transformModuleWithStructure($module),
        ]);
    }

    /**
     * Get module by API name.
     */
    public function showByApiName(string $apiName): JsonResponse
    {
        $module = $this->moduleService->getModuleByApiName($apiName);

        if (!$module) {
            return response()->json(['error' => 'Module not found'], 404);
        }

        return response()->json([
            'module' => $this->transformModuleWithStructure($module),
        ]);
    }

    /**
     * Create a new module.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'singular_name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'display_order' => ['integer', 'min:0'],
            'settings' => ['nullable', 'array'],
            'blocks' => ['nullable', 'array'],
            'blocks.*.name' => ['required_with:blocks', 'string', 'max:255'],
            'blocks.*.type' => ['required_with:blocks', 'string', 'in:section,tab,accordion,card'],
            'blocks.*.display_order' => ['integer', 'min:0'],
            'blocks.*.settings' => ['nullable', 'array'],
            'blocks.*.fields' => ['nullable', 'array'],
            'blocks.*.fields.*.label' => ['required_with:blocks.*.fields', 'string', 'max:255'],
            'blocks.*.fields.*.type' => ['required_with:blocks.*.fields', 'string'],
            'blocks.*.fields.*.description' => ['nullable', 'string'],
            'blocks.*.fields.*.help_text' => ['nullable', 'string'],
            'blocks.*.fields.*.is_required' => ['boolean'],
            'blocks.*.fields.*.is_unique' => ['boolean'],
            'blocks.*.fields.*.is_searchable' => ['boolean'],
            'blocks.*.fields.*.is_filterable' => ['boolean'],
            'blocks.*.fields.*.is_sortable' => ['boolean'],
            'blocks.*.fields.*.default_value' => ['nullable', 'string'],
            'blocks.*.fields.*.display_order' => ['integer', 'min:0'],
            'blocks.*.fields.*.width' => ['integer', 'min:1', 'max:100'],
        ]);

        try {
            $module = $this->moduleService->createModule($validated);

            return response()->json([
                'message' => 'Module created successfully',
                'module' => $this->transformModuleWithStructure($module),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update an existing module.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'singular_name' => ['sometimes', 'required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'display_order' => ['integer', 'min:0'],
            'settings' => ['nullable', 'array'],
        ]);

        try {
            $module = $this->moduleService->updateModule($id, $validated);

            return response()->json([
                'message' => 'Module updated successfully',
                'module' => $this->transformModule($module),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], $e->getMessage() === "Module not found with ID {$id}." ? 404 : 422);
        }
    }

    /**
     * Delete a module.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->moduleService->deleteModule($id);

            return response()->json([
                'message' => 'Module deleted successfully',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle module active status.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $module = $this->moduleService->toggleModuleStatus($id);

            return response()->json([
                'message' => 'Module status toggled successfully',
                'module' => $this->transformModule($module),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Transform module entity to array.
     */
    private function transformModule(Module $module): array
    {
        return [
            'id' => $module->id(),
            'name' => $module->name(),
            'singular_name' => $module->singularName(),
            'api_name' => $module->apiName(),
            'icon' => $module->icon(),
            'description' => $module->description(),
            'is_active' => $module->isActive(),
            'display_order' => $module->displayOrder(),
            'settings' => $module->settings()->jsonSerialize(),
            'created_at' => $module->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $module->updatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Transform module entity with blocks and fields.
     */
    private function transformModuleWithStructure(Module $module): array
    {
        $data = $this->transformModule($module);

        $data['blocks'] = array_map(function ($block) {
            return [
                'id' => $block->id(),
                'name' => $block->name(),
                'type' => $block->type()->value,
                'display_order' => $block->displayOrder(),
                'settings' => $block->settings(),
                'fields' => array_map(function ($field) {
                    return [
                        'id' => $field->id(),
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
                        'validation_rules' => $field->validationRules()->toArray(),
                        'settings' => $field->settings()->jsonSerialize(),
                        'default_value' => $field->defaultValue(),
                        'display_order' => $field->displayOrder(),
                        'width' => $field->width(),
                        'options' => array_map(function ($option) {
                            return [
                                'id' => $option->id(),
                                'label' => $option->label(),
                                'value' => $option->value(),
                                'color' => $option->color(),
                                'is_active' => $option->isActive(),
                                'display_order' => $option->displayOrder(),
                            ];
                        }, $field->options()),
                    ];
                }, $block->fields()),
            ];
        }, $module->blocks());

        return $data;
    }
}
