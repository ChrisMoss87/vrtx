<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Blueprints;

use App\Http\Controllers\Controller;
use App\Models\BlueprintApproval;
use App\Models\BlueprintTransition;
use App\Models\BlueprintTransitionAction;
use App\Models\BlueprintTransitionCondition;
use App\Models\BlueprintTransitionRequirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlueprintTransitionConfigController extends Controller
{
    // ==================== Conditions ====================

    /**
     * Get all conditions for a transition.
     */
    public function getConditions(int $transitionId): JsonResponse
    {
        $transition = BlueprintTransition::findOrFail($transitionId);

        $conditions = $transition->conditions()
            ->with('field:id,api_name,label,type')
            ->orderBy('display_order')
            ->get();

        return response()->json(['conditions' => $conditions]);
    }

    /**
     * Create a new condition.
     */
    public function storeCondition(Request $request, int $transitionId): JsonResponse
    {
        $transition = BlueprintTransition::findOrFail($transitionId);

        $validated = $request->validate([
            'field_id' => 'required|integer|exists:fields,id',
            'operator' => 'required|string|max:50',
            'value' => 'nullable|string',
            'logical_group' => 'sometimes|string|in:AND,OR',
            'display_order' => 'sometimes|integer',
        ]);

        $condition = $transition->conditions()->create([
            'field_id' => $validated['field_id'],
            'operator' => $validated['operator'],
            'value' => $validated['value'] ?? null,
            'logical_group' => $validated['logical_group'] ?? 'AND',
            'display_order' => $validated['display_order'] ?? $transition->conditions()->count(),
        ]);

        $condition->load('field:id,api_name,label,type');

        return response()->json(['condition' => $condition], 201);
    }

    /**
     * Update a condition.
     */
    public function updateCondition(Request $request, int $transitionId, int $conditionId): JsonResponse
    {
        $condition = BlueprintTransitionCondition::where('transition_id', $transitionId)
            ->findOrFail($conditionId);

        $validated = $request->validate([
            'field_id' => 'sometimes|integer|exists:fields,id',
            'operator' => 'sometimes|string|max:50',
            'value' => 'nullable|string',
            'logical_group' => 'sometimes|string|in:AND,OR',
            'display_order' => 'sometimes|integer',
        ]);

        $condition->update($validated);
        $condition->load('field:id,api_name,label,type');

        return response()->json(['condition' => $condition]);
    }

    /**
     * Delete a condition.
     */
    public function destroyCondition(int $transitionId, int $conditionId): JsonResponse
    {
        $condition = BlueprintTransitionCondition::where('transition_id', $transitionId)
            ->findOrFail($conditionId);

        $condition->delete();

        return response()->json(['message' => 'Condition deleted successfully']);
    }

    // ==================== Requirements ====================

    /**
     * Get all requirements for a transition.
     */
    public function getRequirements(int $transitionId): JsonResponse
    {
        $transition = BlueprintTransition::findOrFail($transitionId);

        $requirements = $transition->requirements()
            ->with('field:id,api_name,label,type')
            ->orderBy('display_order')
            ->get();

        return response()->json(['requirements' => $requirements]);
    }

    /**
     * Create a new requirement.
     */
    public function storeRequirement(Request $request, int $transitionId): JsonResponse
    {
        $transition = BlueprintTransition::findOrFail($transitionId);

        $validated = $request->validate([
            'type' => 'required|string|in:mandatory_field,attachment,note,checklist',
            'field_id' => 'nullable|integer|exists:fields,id',
            'label' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'sometimes|boolean',
            'config' => 'nullable|array',
            'display_order' => 'sometimes|integer',
        ]);

        $requirement = $transition->requirements()->create([
            'type' => $validated['type'],
            'field_id' => $validated['field_id'] ?? null,
            'label' => $validated['label'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_required' => $validated['is_required'] ?? true,
            'config' => $validated['config'] ?? null,
            'display_order' => $validated['display_order'] ?? $transition->requirements()->count(),
        ]);

        $requirement->load('field:id,api_name,label,type');

        return response()->json(['requirement' => $requirement], 201);
    }

    /**
     * Update a requirement.
     */
    public function updateRequirement(Request $request, int $transitionId, int $requirementId): JsonResponse
    {
        $requirement = BlueprintTransitionRequirement::where('transition_id', $transitionId)
            ->findOrFail($requirementId);

        $validated = $request->validate([
            'type' => 'sometimes|string|in:mandatory_field,attachment,note,checklist',
            'field_id' => 'nullable|integer|exists:fields,id',
            'label' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'sometimes|boolean',
            'config' => 'nullable|array',
            'display_order' => 'sometimes|integer',
        ]);

        $requirement->update($validated);
        $requirement->load('field:id,api_name,label,type');

        return response()->json(['requirement' => $requirement]);
    }

    /**
     * Delete a requirement.
     */
    public function destroyRequirement(int $transitionId, int $requirementId): JsonResponse
    {
        $requirement = BlueprintTransitionRequirement::where('transition_id', $transitionId)
            ->findOrFail($requirementId);

        $requirement->delete();

        return response()->json(['message' => 'Requirement deleted successfully']);
    }

    // ==================== Actions ====================

    /**
     * Get all actions for a transition.
     */
    public function getActions(int $transitionId): JsonResponse
    {
        $transition = BlueprintTransition::findOrFail($transitionId);

        $actions = $transition->actions()
            ->orderBy('display_order')
            ->get();

        return response()->json(['actions' => $actions]);
    }

    /**
     * Create a new action.
     */
    public function storeAction(Request $request, int $transitionId): JsonResponse
    {
        $transition = BlueprintTransition::findOrFail($transitionId);

        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'config' => 'required|array',
            'display_order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $action = $transition->actions()->create([
            'type' => $validated['type'],
            'config' => $validated['config'],
            'display_order' => $validated['display_order'] ?? $transition->actions()->count(),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['action' => $action], 201);
    }

    /**
     * Update an action.
     */
    public function updateAction(Request $request, int $transitionId, int $actionId): JsonResponse
    {
        $action = BlueprintTransitionAction::where('transition_id', $transitionId)
            ->findOrFail($actionId);

        $validated = $request->validate([
            'type' => 'sometimes|string|max:50',
            'config' => 'sometimes|array',
            'display_order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $action->update($validated);

        return response()->json(['action' => $action]);
    }

    /**
     * Delete an action.
     */
    public function destroyAction(int $transitionId, int $actionId): JsonResponse
    {
        $action = BlueprintTransitionAction::where('transition_id', $transitionId)
            ->findOrFail($actionId);

        $action->delete();

        return response()->json(['message' => 'Action deleted successfully']);
    }

    // ==================== Approval ====================

    /**
     * Get approval configuration for a transition.
     */
    public function getApproval(int $transitionId): JsonResponse
    {
        $transition = BlueprintTransition::findOrFail($transitionId);

        $approval = $transition->approval;

        return response()->json(['approval' => $approval]);
    }

    /**
     * Set or update approval configuration.
     */
    public function setApproval(Request $request, int $transitionId): JsonResponse
    {
        $transition = BlueprintTransition::findOrFail($transitionId);

        $validated = $request->validate([
            'approval_type' => 'required|string|in:specific_users,role_based,manager,field_value',
            'config' => 'required|array',
            'require_all' => 'sometimes|boolean',
            'auto_reject_days' => 'nullable|integer|min:1',
            'notify_on_pending' => 'sometimes|boolean',
            'notify_on_complete' => 'sometimes|boolean',
        ]);

        $approval = BlueprintApproval::updateOrCreate(
            ['transition_id' => $transitionId],
            [
                'approval_type' => $validated['approval_type'],
                'config' => $validated['config'],
                'require_all' => $validated['require_all'] ?? false,
                'auto_reject_days' => $validated['auto_reject_days'] ?? null,
                'notify_on_pending' => $validated['notify_on_pending'] ?? true,
                'notify_on_complete' => $validated['notify_on_complete'] ?? true,
            ]
        );

        return response()->json(['approval' => $approval]);
    }

    /**
     * Remove approval configuration.
     */
    public function removeApproval(int $transitionId): JsonResponse
    {
        BlueprintApproval::where('transition_id', $transitionId)->delete();

        return response()->json(['message' => 'Approval configuration removed']);
    }
}
