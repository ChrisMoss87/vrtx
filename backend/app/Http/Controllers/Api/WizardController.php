<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Wizard\Services\WizardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WizardController extends Controller
{
    public function __construct(
        private WizardService $wizardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wizards = $this->wizardService->listWizards(
            moduleId: $request->has('module_id') ? (int) $request->module_id : null,
            type: $request->get('type'),
            activeOnly: $request->boolean('active_only', false),
        );

        return response()->json([
            'data' => $wizards->map(fn ($wizard) => $this->wizardService->formatWizard($wizard->toArray())),
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

        $wizard = $this->wizardService->createWizard(
            data: collect($validated)->except('steps')->toArray(),
            steps: $validated['steps'],
            createdBy: $request->user()->id,
        );

        return response()->json([
            'data' => $this->wizardService->formatWizard($wizard),
            'message' => 'Wizard created successfully',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $wizard = $this->wizardService->getWizard($id);

        if (!$wizard) {
            return response()->json(['message' => 'Wizard not found'], 404);
        }

        return response()->json([
            'data' => $this->wizardService->formatWizard($wizard),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->wizardService->wizardExists($id)) {
            return response()->json(['message' => 'Wizard not found'], 404);
        }

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

        $wizard = $this->wizardService->updateWizard(
            id: $id,
            data: collect($validated)->except('steps')->toArray(),
            steps: $validated['steps'] ?? null,
        );

        return response()->json([
            'data' => $this->wizardService->formatWizard($wizard),
            'message' => 'Wizard updated successfully',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        if (!$this->wizardService->deleteWizard($id)) {
            return response()->json(['message' => 'Wizard not found'], 404);
        }

        return response()->json(['message' => 'Wizard deleted successfully']);
    }

    public function duplicate(int $id): JsonResponse
    {
        if (!$this->wizardService->wizardExists($id)) {
            return response()->json(['message' => 'Wizard not found'], 404);
        }

        $clone = $this->wizardService->duplicateWizard($id);

        return response()->json([
            'data' => $this->wizardService->formatWizard($clone),
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

        $this->wizardService->reorderWizards($validated['wizards']);

        return response()->json(['message' => 'Wizards reordered successfully']);
    }

    public function toggleActive(int $id): JsonResponse
    {
        if (!$this->wizardService->wizardExists($id)) {
            return response()->json(['message' => 'Wizard not found'], 404);
        }

        $wizard = $this->wizardService->toggleActive($id);

        return response()->json([
            'data' => $this->wizardService->formatWizard($wizard),
            'message' => $wizard['is_active'] ? 'Wizard activated' : 'Wizard deactivated',
        ]);
    }

    public function forModule(int $moduleId): JsonResponse
    {
        $wizards = $this->wizardService->getWizardsForModule($moduleId);

        return response()->json([
            'data' => $wizards->map(fn ($wizard) => $this->wizardService->formatWizard($wizard->toArray())),
        ]);
    }
}
