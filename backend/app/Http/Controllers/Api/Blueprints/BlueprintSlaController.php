<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Blueprints;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\BlueprintSla;
use App\Models\BlueprintSlaEscalation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlueprintSlaController extends Controller
{
    // ==================== SLA CRUD ====================

    /**
     * Get all SLAs for a blueprint.
     */
    public function index(int $blueprintId): JsonResponse
    {
        $blueprint = Blueprint::findOrFail($blueprintId);

        $slas = $blueprint->slas()
            ->with(['state:id,name,color', 'escalations'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['slas' => $slas]);
    }

    /**
     * Create a new SLA.
     */
    public function store(Request $request, int $blueprintId): JsonResponse
    {
        $blueprint = Blueprint::findOrFail($blueprintId);

        $validated = $request->validate([
            'state_id' => 'required|integer|exists:blueprint_states,id',
            'name' => 'required|string|max:255',
            'duration_hours' => 'required|integer|min:1',
            'business_hours_only' => 'sometimes|boolean',
            'exclude_weekends' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        // Verify state belongs to this blueprint
        $stateExists = $blueprint->states()->where('id', $validated['state_id'])->exists();
        if (!$stateExists) {
            return response()->json([
                'message' => 'State does not belong to this blueprint',
            ], 422);
        }

        // Check if SLA already exists for this state
        $existingSla = $blueprint->slas()->where('state_id', $validated['state_id'])->first();
        if ($existingSla) {
            return response()->json([
                'message' => 'An SLA already exists for this state',
            ], 422);
        }

        $sla = $blueprint->slas()->create([
            'state_id' => $validated['state_id'],
            'name' => $validated['name'],
            'duration_hours' => $validated['duration_hours'],
            'business_hours_only' => $validated['business_hours_only'] ?? false,
            'exclude_weekends' => $validated['exclude_weekends'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $sla->load(['state:id,name,color', 'escalations']);

        return response()->json(['sla' => $sla], 201);
    }

    /**
     * Get a specific SLA.
     */
    public function show(int $blueprintId, int $slaId): JsonResponse
    {
        $sla = BlueprintSla::where('blueprint_id', $blueprintId)
            ->with(['state:id,name,color', 'escalations'])
            ->findOrFail($slaId);

        return response()->json(['sla' => $sla]);
    }

    /**
     * Update an SLA.
     */
    public function update(Request $request, int $blueprintId, int $slaId): JsonResponse
    {
        $sla = BlueprintSla::where('blueprint_id', $blueprintId)
            ->findOrFail($slaId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'duration_hours' => 'sometimes|integer|min:1',
            'business_hours_only' => 'sometimes|boolean',
            'exclude_weekends' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $sla->update($validated);
        $sla->load(['state:id,name,color', 'escalations']);

        return response()->json(['sla' => $sla]);
    }

    /**
     * Delete an SLA.
     */
    public function destroy(int $blueprintId, int $slaId): JsonResponse
    {
        $sla = BlueprintSla::where('blueprint_id', $blueprintId)
            ->findOrFail($slaId);

        $sla->delete();

        return response()->json(['message' => 'SLA deleted successfully']);
    }

    // ==================== Escalation CRUD ====================

    /**
     * Get all escalations for an SLA.
     */
    public function getEscalations(int $slaId): JsonResponse
    {
        $sla = BlueprintSla::findOrFail($slaId);

        $escalations = $sla->escalations()
            ->orderBy('display_order')
            ->get();

        return response()->json(['escalations' => $escalations]);
    }

    /**
     * Create a new escalation.
     */
    public function storeEscalation(Request $request, int $slaId): JsonResponse
    {
        $sla = BlueprintSla::findOrFail($slaId);

        $validated = $request->validate([
            'trigger_type' => 'required|string|in:approaching,breached',
            'trigger_value' => 'nullable|integer|min:1|max:100',
            'action_type' => 'required|string|max:50',
            'config' => 'required|array',
            'display_order' => 'sometimes|integer',
        ]);

        // trigger_value is required for 'approaching' type
        if ($validated['trigger_type'] === 'approaching' && empty($validated['trigger_value'])) {
            return response()->json([
                'message' => 'trigger_value is required for approaching type',
                'errors' => ['trigger_value' => ['The trigger value field is required when trigger type is approaching.']],
            ], 422);
        }

        $escalation = $sla->escalations()->create([
            'trigger_type' => $validated['trigger_type'],
            'trigger_value' => $validated['trigger_value'] ?? null,
            'action_type' => $validated['action_type'],
            'config' => $validated['config'],
            'display_order' => $validated['display_order'] ?? $sla->escalations()->count(),
        ]);

        return response()->json(['escalation' => $escalation], 201);
    }

    /**
     * Update an escalation.
     */
    public function updateEscalation(Request $request, int $slaId, int $escalationId): JsonResponse
    {
        $escalation = BlueprintSlaEscalation::where('sla_id', $slaId)
            ->findOrFail($escalationId);

        $validated = $request->validate([
            'trigger_type' => 'sometimes|string|in:approaching,breached',
            'trigger_value' => 'nullable|integer|min:1|max:100',
            'action_type' => 'sometimes|string|max:50',
            'config' => 'sometimes|array',
            'display_order' => 'sometimes|integer',
        ]);

        $escalation->update($validated);

        return response()->json(['escalation' => $escalation]);
    }

    /**
     * Delete an escalation.
     */
    public function destroyEscalation(int $slaId, int $escalationId): JsonResponse
    {
        $escalation = BlueprintSlaEscalation::where('sla_id', $slaId)
            ->findOrFail($escalationId);

        $escalation->delete();

        return response()->json(['message' => 'Escalation deleted successfully']);
    }
}
