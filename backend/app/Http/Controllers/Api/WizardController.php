<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wizard;
use App\Models\WizardStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WizardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Wizard::with(['steps', 'module', 'creator']);

        if ($request->has('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $wizards = $query->orderBy('display_order')->get();

        return response()->json([
            'data' => $wizards->map(fn ($wizard) => $this->formatWizard($wizard)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'api_name' => 'nullable|string|max:255|unique:wizards,api_name',
            'description' => 'nullable|string',
            'module_id' => 'nullable|exists:modules,id',
            'type' => 'required|in:record_creation,record_edit,standalone',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'settings' => 'nullable|array',
            'settings.showProgress' => 'boolean',
            'settings.allowClickNavigation' => 'boolean',
            'settings.saveAsDraft' => 'boolean',
            'steps' => 'required|array|min:1',
            'steps.*.title' => 'required|string|max:255',
            'steps.*.description' => 'nullable|string',
            'steps.*.type' => 'required|in:form,review,confirmation,custom',
            'steps.*.fields' => 'nullable|array',
            'steps.*.can_skip' => 'boolean',
            'steps.*.conditional_logic' => 'nullable|array',
            'steps.*.validation_rules' => 'nullable|array',
        ]);

        $wizard = DB::transaction(function () use ($validated, $request) {
            // If setting as default, unset other defaults for this module
            if ($validated['is_default'] ?? false) {
                Wizard::where('module_id', $validated['module_id'] ?? null)
                    ->where('type', $validated['type'])
                    ->update(['is_default' => false]);
            }

            $wizard = Wizard::create([
                'name' => $validated['name'],
                'api_name' => $validated['api_name'] ?? Str::snake($validated['name']),
                'description' => $validated['description'] ?? null,
                'module_id' => $validated['module_id'] ?? null,
                'type' => $validated['type'],
                'is_active' => $validated['is_active'] ?? true,
                'is_default' => $validated['is_default'] ?? false,
                'settings' => $validated['settings'] ?? [
                    'showProgress' => true,
                    'allowClickNavigation' => false,
                    'saveAsDraft' => true,
                ],
                'created_by' => $request->user()->id,
                'display_order' => Wizard::max('display_order') + 1,
            ]);

            foreach ($validated['steps'] as $index => $stepData) {
                WizardStep::create([
                    'wizard_id' => $wizard->id,
                    'title' => $stepData['title'],
                    'description' => $stepData['description'] ?? null,
                    'type' => $stepData['type'],
                    'fields' => $stepData['fields'] ?? [],
                    'can_skip' => $stepData['can_skip'] ?? false,
                    'display_order' => $index,
                    'conditional_logic' => $stepData['conditional_logic'] ?? null,
                    'validation_rules' => $stepData['validation_rules'] ?? null,
                ]);
            }

            return $wizard->load(['steps', 'module', 'creator']);
        });

        return response()->json([
            'data' => $this->formatWizard($wizard),
            'message' => 'Wizard created successfully',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $wizard = Wizard::with(['steps', 'module', 'creator'])->findOrFail($id);

        return response()->json([
            'data' => $this->formatWizard($wizard),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $wizard = Wizard::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'api_name' => ['sometimes', 'string', 'max:255', Rule::unique('wizards', 'api_name')->ignore($id)],
            'description' => 'nullable|string',
            'module_id' => 'nullable|exists:modules,id',
            'type' => 'sometimes|in:record_creation,record_edit,standalone',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'settings' => 'nullable|array',
            'steps' => 'sometimes|array|min:1',
            'steps.*.id' => 'nullable|integer',
            'steps.*.title' => 'required|string|max:255',
            'steps.*.description' => 'nullable|string',
            'steps.*.type' => 'required|in:form,review,confirmation,custom',
            'steps.*.fields' => 'nullable|array',
            'steps.*.can_skip' => 'boolean',
            'steps.*.conditional_logic' => 'nullable|array',
            'steps.*.validation_rules' => 'nullable|array',
        ]);

        $wizard = DB::transaction(function () use ($wizard, $validated) {
            // If setting as default, unset other defaults
            if (($validated['is_default'] ?? false) && !$wizard->is_default) {
                Wizard::where('module_id', $validated['module_id'] ?? $wizard->module_id)
                    ->where('type', $validated['type'] ?? $wizard->type)
                    ->where('id', '!=', $wizard->id)
                    ->update(['is_default' => false]);
            }

            $wizard->update(collect($validated)->except('steps')->toArray());

            // Update steps if provided
            if (isset($validated['steps'])) {
                $existingStepIds = [];

                foreach ($validated['steps'] as $index => $stepData) {
                    if (isset($stepData['id'])) {
                        // Update existing step
                        $step = WizardStep::find($stepData['id']);
                        if ($step && $step->wizard_id === $wizard->id) {
                            $step->update([
                                'title' => $stepData['title'],
                                'description' => $stepData['description'] ?? null,
                                'type' => $stepData['type'],
                                'fields' => $stepData['fields'] ?? [],
                                'can_skip' => $stepData['can_skip'] ?? false,
                                'display_order' => $index,
                                'conditional_logic' => $stepData['conditional_logic'] ?? null,
                                'validation_rules' => $stepData['validation_rules'] ?? null,
                            ]);
                            $existingStepIds[] = $step->id;
                        }
                    } else {
                        // Create new step
                        $step = WizardStep::create([
                            'wizard_id' => $wizard->id,
                            'title' => $stepData['title'],
                            'description' => $stepData['description'] ?? null,
                            'type' => $stepData['type'],
                            'fields' => $stepData['fields'] ?? [],
                            'can_skip' => $stepData['can_skip'] ?? false,
                            'display_order' => $index,
                            'conditional_logic' => $stepData['conditional_logic'] ?? null,
                            'validation_rules' => $stepData['validation_rules'] ?? null,
                        ]);
                        $existingStepIds[] = $step->id;
                    }
                }

                // Delete removed steps
                WizardStep::where('wizard_id', $wizard->id)
                    ->whereNotIn('id', $existingStepIds)
                    ->delete();
            }

            return $wizard->fresh(['steps', 'module', 'creator']);
        });

        return response()->json([
            'data' => $this->formatWizard($wizard),
            'message' => 'Wizard updated successfully',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $wizard = Wizard::findOrFail($id);
        $wizard->delete();

        return response()->json([
            'message' => 'Wizard deleted successfully',
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $wizard = Wizard::with('steps')->findOrFail($id);
        $clone = $wizard->duplicate();

        return response()->json([
            'data' => $this->formatWizard($clone->load(['steps', 'module', 'creator'])),
            'message' => 'Wizard duplicated successfully',
        ], 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wizards' => 'required|array',
            'wizards.*.id' => 'required|exists:wizards,id',
            'wizards.*.display_order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['wizards'] as $item) {
                Wizard::where('id', $item['id'])->update(['display_order' => $item['display_order']]);
            }
        });

        return response()->json([
            'message' => 'Wizards reordered successfully',
        ]);
    }

    public function toggleActive(int $id): JsonResponse
    {
        $wizard = Wizard::findOrFail($id);
        $wizard->update(['is_active' => !$wizard->is_active]);

        return response()->json([
            'data' => $this->formatWizard($wizard->fresh(['steps', 'module', 'creator'])),
            'message' => $wizard->is_active ? 'Wizard activated' : 'Wizard deactivated',
        ]);
    }

    public function forModule(int $moduleId): JsonResponse
    {
        $wizards = Wizard::with('steps')
            ->where('module_id', $moduleId)
            ->active()
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'data' => $wizards->map(fn ($wizard) => $this->formatWizard($wizard)),
        ]);
    }

    private function formatWizard(Wizard $wizard): array
    {
        return [
            'id' => $wizard->id,
            'name' => $wizard->name,
            'api_name' => $wizard->api_name,
            'description' => $wizard->description,
            'type' => $wizard->type,
            'is_active' => $wizard->is_active,
            'is_default' => $wizard->is_default,
            'settings' => $wizard->settings ?? [
                'showProgress' => true,
                'allowClickNavigation' => false,
                'saveAsDraft' => true,
            ],
            'display_order' => $wizard->display_order,
            'module' => $wizard->module ? [
                'id' => $wizard->module->id,
                'name' => $wizard->module->name,
                'api_name' => $wizard->module->api_name,
            ] : null,
            'creator' => $wizard->creator ? [
                'id' => $wizard->creator->id,
                'name' => $wizard->creator->name,
            ] : null,
            'steps' => $wizard->steps->map(fn ($step) => [
                'id' => $step->id,
                'title' => $step->title,
                'description' => $step->description,
                'type' => $step->type,
                'fields' => $step->fields ?? [],
                'can_skip' => $step->can_skip,
                'display_order' => $step->display_order,
                'conditional_logic' => $step->conditional_logic,
                'validation_rules' => $step->validation_rules,
            ])->toArray(),
            'step_count' => $wizard->steps->count(),
            'field_count' => $wizard->steps->sum(fn ($step) => count($step->fields ?? [])),
            'created_at' => $wizard->created_at?->toIso8601String(),
            'updated_at' => $wizard->updated_at?->toIso8601String(),
        ];
    }
}
