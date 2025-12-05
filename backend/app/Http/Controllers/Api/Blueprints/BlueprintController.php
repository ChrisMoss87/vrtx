<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Blueprints;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\BlueprintState;
use App\Models\BlueprintTransition;
use App\Services\Blueprint\BlueprintEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BlueprintController extends Controller
{
    public function __construct(
        protected BlueprintEngine $engine
    ) {}

    /**
     * Get all blueprints.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Blueprint::with(['module', 'field', 'states', 'transitions']);

            if ($request->has('module_id')) {
                $query->where('module_id', $request->input('module_id'));
            }

            if ($request->has('active')) {
                $query->where('is_active', $request->boolean('active'));
            }

            $blueprints = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'blueprints' => $blueprints,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch blueprints',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new blueprint.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'module_id' => 'required|exists:modules,id',
                'field_id' => 'required|exists:fields,id',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'sync_states_from_field' => 'nullable|boolean',
            ]);

            // Check if blueprint already exists for this field
            $existing = Blueprint::where('module_id', $validated['module_id'])
                ->where('field_id', $validated['field_id'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'A blueprint already exists for this field',
                    'blueprint' => $existing,
                ], Response::HTTP_CONFLICT);
            }

            $blueprint = DB::transaction(function () use ($validated, $request) {
                $blueprint = Blueprint::create([
                    'name' => $validated['name'],
                    'module_id' => $validated['module_id'],
                    'field_id' => $validated['field_id'],
                    'description' => $validated['description'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                ]);

                // Optionally sync states from field options
                if ($request->boolean('sync_states_from_field', true)) {
                    $this->engine->syncStatesFromFieldOptions($blueprint);
                }

                return $blueprint;
            });

            return response()->json([
                'success' => true,
                'message' => 'Blueprint created successfully',
                'blueprint' => $blueprint->load(['module', 'field', 'states', 'transitions']),
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
                'message' => 'Failed to create blueprint',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a single blueprint.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $blueprint = Blueprint::with([
                'module',
                'field',
                'states',
                'transitions.fromState',
                'transitions.toState',
                'transitions.conditions.field',
                'transitions.requirements.field',
                'transitions.actions',
                'transitions.approval',
                'slas.state',
                'slas.escalations',
            ])->find($id);

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'blueprint' => $blueprint,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch blueprint',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a blueprint.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $blueprint = Blueprint::find($id);

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'layout_data' => 'nullable|array',
            ]);

            $blueprint->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Blueprint updated successfully',
                'blueprint' => $blueprint->fresh()->load(['module', 'field', 'states', 'transitions']),
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
                'message' => 'Failed to update blueprint',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update blueprint layout (node positions).
     */
    public function updateLayout(Request $request, int $id): JsonResponse
    {
        try {
            $blueprint = Blueprint::find($id);

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'layout_data' => 'required|array',
            ]);

            $blueprint->update(['layout_data' => $validated['layout_data']]);

            return response()->json([
                'success' => true,
                'message' => 'Layout saved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save layout',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a blueprint.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $blueprint = Blueprint::find($id);

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $blueprint->delete();

            return response()->json([
                'success' => true,
                'message' => 'Blueprint deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete blueprint',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Toggle blueprint active status.
     */
    public function toggleActive(int $id): JsonResponse
    {
        try {
            $blueprint = Blueprint::find($id);

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $blueprint->update(['is_active' => !$blueprint->is_active]);

            return response()->json([
                'success' => true,
                'message' => $blueprint->is_active ? 'Blueprint activated' : 'Blueprint deactivated',
                'blueprint' => $blueprint,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle blueprint status',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sync states from field options.
     */
    public function syncStates(int $id): JsonResponse
    {
        try {
            $blueprint = Blueprint::find($id);

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $this->engine->syncStatesFromFieldOptions($blueprint);

            return response()->json([
                'success' => true,
                'message' => 'States synced from field options',
                'blueprint' => $blueprint->fresh()->load(['states']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync states',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================== State Management ====================

    /**
     * Get states for a blueprint.
     */
    public function states(int $id): JsonResponse
    {
        try {
            $blueprint = Blueprint::find($id);

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'states' => $blueprint->states,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch states',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a state.
     */
    public function storeState(Request $request, int $id): JsonResponse
    {
        try {
            $blueprint = Blueprint::find($id);

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'field_option_value' => 'nullable|string',
                'color' => 'nullable|string|max:7',
                'is_initial' => 'nullable|boolean',
                'is_terminal' => 'nullable|boolean',
                'position_x' => 'nullable|integer',
                'position_y' => 'nullable|integer',
                'metadata' => 'nullable|array',
            ]);

            // If setting as initial, unset other initial states
            if ($request->boolean('is_initial')) {
                $blueprint->states()->where('is_initial', true)->update(['is_initial' => false]);
            }

            $state = $blueprint->states()->create($validated);

            return response()->json([
                'success' => true,
                'message' => 'State created successfully',
                'state' => $state,
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
                'message' => 'Failed to create state',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a state.
     */
    public function updateState(Request $request, int $id, int $stateId): JsonResponse
    {
        try {
            $state = BlueprintState::where('blueprint_id', $id)->find($stateId);

            if (!$state) {
                return response()->json([
                    'success' => false,
                    'message' => 'State not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'field_option_value' => 'nullable|string',
                'color' => 'nullable|string|max:7',
                'is_initial' => 'nullable|boolean',
                'is_terminal' => 'nullable|boolean',
                'position_x' => 'nullable|integer',
                'position_y' => 'nullable|integer',
                'metadata' => 'nullable|array',
            ]);

            // If setting as initial, unset other initial states
            if ($request->boolean('is_initial') && !$state->is_initial) {
                BlueprintState::where('blueprint_id', $id)
                    ->where('is_initial', true)
                    ->update(['is_initial' => false]);
            }

            $state->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'State updated successfully',
                'state' => $state->fresh(),
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
                'message' => 'Failed to update state',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a state.
     */
    public function destroyState(int $id, int $stateId): JsonResponse
    {
        try {
            $state = BlueprintState::where('blueprint_id', $id)->find($stateId);

            if (!$state) {
                return response()->json([
                    'success' => false,
                    'message' => 'State not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if state has transitions
            if ($state->incomingTransitions()->count() > 0 || $state->outgoingTransitions()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete state with existing transitions',
                ], Response::HTTP_CONFLICT);
            }

            $state->delete();

            return response()->json([
                'success' => true,
                'message' => 'State deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete state',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ==================== Transition Management ====================

    /**
     * Get transitions for a blueprint.
     */
    public function transitions(int $id): JsonResponse
    {
        try {
            $blueprint = Blueprint::find($id);

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $transitions = $blueprint->transitions()
                ->with(['fromState', 'toState', 'conditions.field', 'requirements.field', 'actions', 'approval'])
                ->get();

            return response()->json([
                'success' => true,
                'transitions' => $transitions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transitions',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a transition.
     */
    public function storeTransition(Request $request, int $id): JsonResponse
    {
        try {
            $blueprint = Blueprint::find($id);

            if (!$blueprint) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blueprint not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'from_state_id' => 'nullable|exists:blueprint_states,id',
                'to_state_id' => 'required|exists:blueprint_states,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'button_label' => 'nullable|string|max:100',
                'display_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ]);

            $transition = $blueprint->transitions()->create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Transition created successfully',
                'transition' => $transition->load(['fromState', 'toState']),
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
                'message' => 'Failed to create transition',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a transition.
     */
    public function updateTransition(Request $request, int $id, int $transitionId): JsonResponse
    {
        try {
            $transition = BlueprintTransition::where('blueprint_id', $id)->find($transitionId);

            if (!$transition) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transition not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validate([
                'from_state_id' => 'nullable|exists:blueprint_states,id',
                'to_state_id' => 'sometimes|exists:blueprint_states,id',
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'button_label' => 'nullable|string|max:100',
                'display_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ]);

            $transition->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Transition updated successfully',
                'transition' => $transition->fresh()->load(['fromState', 'toState']),
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
                'message' => 'Failed to update transition',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a transition.
     */
    public function destroyTransition(int $id, int $transitionId): JsonResponse
    {
        try {
            $transition = BlueprintTransition::where('blueprint_id', $id)->find($transitionId);

            if (!$transition) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transition not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $transition->delete();

            return response()->json([
                'success' => true,
                'message' => 'Transition deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transition',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
