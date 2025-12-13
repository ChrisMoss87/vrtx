<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Quotas;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Services\Quotas\GoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function __construct(
        protected GoalService $goalService
    ) {}

    /**
     * List goals with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Goal::with(['milestones', 'user']);

        if ($request->has('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->has('goal_type')) {
            $query->type($request->goal_type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('current')) {
            $query->current();
        }

        if ($request->boolean('active')) {
            $query->active();
        }

        $goals = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

        return response()->json($goals);
    }

    /**
     * Create a new goal.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'goal_type' => 'required|string|in:individual,team,company',
            'user_id' => 'nullable|exists:users,id',
            'metric_type' => 'required|string|in:revenue,deals,leads,calls,meetings,activities,custom',
            'metric_field' => 'nullable|string|max:100',
            'module_api_name' => 'nullable|string|max:100',
            'target_value' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'milestones' => 'nullable|array',
            'milestones.*.name' => 'required|string|max:255',
            'milestones.*.target_value' => 'required|numeric|min:0',
            'milestones.*.target_date' => 'nullable|date',
        ]);

        // For individual goals, default to current user if not specified
        if ($validated['goal_type'] === Goal::TYPE_INDIVIDUAL && empty($validated['user_id'])) {
            $validated['user_id'] = auth()->id();
        }

        $goal = $this->goalService->create($validated, auth()->id());

        return response()->json([
            'data' => $goal,
            'message' => 'Goal created successfully',
        ], 201);
    }

    /**
     * Show a specific goal.
     */
    public function show(Goal $goal): JsonResponse
    {
        $goal->load(['milestones', 'user', 'progressLogs' => function ($q) {
            $q->orderByDesc('log_date')->limit(30);
        }]);

        return response()->json([
            'data' => $goal,
        ]);
    }

    /**
     * Update a goal.
     */
    public function update(Request $request, Goal $goal): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'target_value' => 'sometimes|numeric|min:0',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'milestones' => 'nullable|array',
            'milestones.*.id' => 'nullable|exists:goal_milestones,id',
            'milestones.*.name' => 'required|string|max:255',
            'milestones.*.target_value' => 'required|numeric|min:0',
            'milestones.*.target_date' => 'nullable|date',
        ]);

        $goal = $this->goalService->update($goal, $validated);

        return response()->json([
            'data' => $goal,
            'message' => 'Goal updated successfully',
        ]);
    }

    /**
     * Delete a goal.
     */
    public function destroy(Goal $goal): JsonResponse
    {
        $goal->delete();

        return response()->json([
            'message' => 'Goal deleted successfully',
        ]);
    }

    /**
     * Get goal progress details.
     */
    public function progress(Goal $goal): JsonResponse
    {
        $progress = $this->goalService->getGoalProgress($goal);

        return response()->json([
            'data' => $progress,
        ]);
    }

    /**
     * Update goal progress manually.
     */
    public function updateProgress(Request $request, Goal $goal): JsonResponse
    {
        $validated = $request->validate([
            'current_value' => 'required|numeric|min:0',
            'source' => 'nullable|string|max:100',
        ]);

        $this->goalService->updateGoalProgress(
            $goal,
            $validated['current_value'],
            $validated['source'] ?? 'manual'
        );

        return response()->json([
            'data' => $goal->fresh(['milestones', 'progressLogs']),
            'message' => 'Progress updated successfully',
        ]);
    }

    /**
     * Get my goals.
     */
    public function myGoals(Request $request): JsonResponse
    {
        $goals = $this->goalService->getUserGoals(auth()->id(), [
            'status' => $request->get('status'),
            'current' => $request->boolean('current'),
        ]);

        return response()->json([
            'data' => $goals,
        ]);
    }

    /**
     * Get active goals by type.
     */
    public function active(): JsonResponse
    {
        $goals = $this->goalService->getActiveGoals(auth()->id());

        return response()->json([
            'data' => $goals,
        ]);
    }

    /**
     * Pause a goal.
     */
    public function pause(Goal $goal): JsonResponse
    {
        $goal->pause();

        return response()->json([
            'data' => $goal,
            'message' => 'Goal paused',
        ]);
    }

    /**
     * Resume a goal.
     */
    public function resume(Goal $goal): JsonResponse
    {
        $goal->resume();

        return response()->json([
            'data' => $goal,
            'message' => 'Goal resumed',
        ]);
    }

    /**
     * Get goal statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->goalService->getStats($request->get('user_id'));

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get goal types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => Goal::getGoalTypes(),
        ]);
    }
}
