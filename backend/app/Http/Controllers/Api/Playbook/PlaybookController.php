<?php

namespace App\Http\Controllers\Api\Playbook;

use App\Application\Services\Playbook\PlaybookApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Playbook\PlaybookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PlaybookController extends Controller
{
    public function __construct(
        protected PlaybookApplicationService $playbookApplicationService,
        protected PlaybookService $playbookService
    ) {}

    /**
     * List all playbooks
     */
    public function index(Request $request): JsonResponse
    {
        $query = Playbook::with(['phases', 'defaultOwner', 'creator'])
            ->withCount(['tasks', 'instances']);

        if ($request->has('active_only') && $request->boolean('active_only')) {
            $query->active();
        }

        if ($request->has('module')) {
            $query->forModule($request->input('module'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $playbooks = $query->orderBy('display_order')
            ->orderBy('name')
            ->paginate($request->input('per_page', 20));

        return response()->json($playbooks);
    }

    /**
     * Get a single playbook
     */
    public function show(int $id): JsonResponse
    {
        $playbook = Playbook::with([
            'phases.tasks',
            'tasks.phase',
            'goals',
            'defaultOwner',
            'creator',
        ])->findOrFail($id);

        $stats = $this->playbookService->getPlaybookStats($playbook);

        return response()->json([
            'playbook' => $playbook,
            'stats' => $stats,
        ]);
    }

    /**
     * Create a new playbook
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_module' => 'nullable|string',
            'trigger_condition' => 'nullable|string',
            'trigger_config' => 'nullable|array',
            'estimated_days' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'auto_assign' => 'sometimes|boolean',
            'default_owner_id' => 'nullable|exists:users,id',
            'tags' => 'nullable|array',
        ]);

        $validated['created_by'] = auth()->id();

        $playbook = DB::table('playbooks')->insertGetId($validated);

        return response()->json([
            'playbook' => $playbook->load(['defaultOwner', 'creator']),
            'message' => 'Playbook created successfully',
        ], 201);
    }

    /**
     * Update a playbook
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $playbook = DB::table('playbooks')->where('id', $id)->first();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'trigger_module' => 'nullable|string',
            'trigger_condition' => 'nullable|string',
            'trigger_config' => 'nullable|array',
            'estimated_days' => 'nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'auto_assign' => 'sometimes|boolean',
            'default_owner_id' => 'nullable|exists:users,id',
            'tags' => 'nullable|array',
            'display_order' => 'sometimes|integer',
        ]);

        // Update slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $playbook->name) {
            $validated['slug'] = Str::slug($validated['name']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (DB::table('playbooks')->where('slug', $validated['slug'])->where('id', '!=', $id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        $playbook->update($validated);

        return response()->json([
            'playbook' => $playbook->fresh()->load(['phases', 'tasks', 'goals', 'defaultOwner']),
        ]);
    }

    /**
     * Delete a playbook
     */
    public function destroy(int $id): JsonResponse
    {
        $playbook = DB::table('playbooks')->where('id', $id)->first();

        // Check for active instances
        $activeInstances = $playbook->instances()->where('status', 'active')->count();
        if ($activeInstances > 0) {
            return response()->json([
                'message' => 'Cannot delete playbook with active instances',
            ], 422);
        }

        $playbook->delete();

        return response()->json(['message' => 'Playbook deleted']);
    }

    /**
     * Add a phase to a playbook
     */
    public function addPhase(Request $request, int $playbookId): JsonResponse
    {
        $playbook = DB::table('playbooks')->where('id', $playbookId)->first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_days' => 'nullable|integer|min:0',
        ]);

        $maxOrder = $playbook->phases()->max('display_order') ?? -1;
        $validated['display_order'] = $maxOrder + 1;

        $phase = $playbook->phases()->create($validated);

        return response()->json([
            'phase' => $phase,
            'message' => 'Phase added successfully',
        ], 201);
    }

    /**
     * Update a phase
     */
    public function updatePhase(Request $request, int $playbookId, int $phaseId): JsonResponse
    {
        $phase = DB::table('playbook_phases')->where('playbook_id', $playbookId)
            ->findOrFail($phaseId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'target_days' => 'nullable|integer|min:0',
            'display_order' => 'sometimes|integer',
        ]);

        $phase->update($validated);

        return response()->json(['phase' => $phase]);
    }

    /**
     * Delete a phase
     */
    public function deletePhase(int $playbookId, int $phaseId): JsonResponse
    {
        $phase = DB::table('playbook_phases')->where('playbook_id', $playbookId)
            ->findOrFail($phaseId);

        // Move tasks to no phase
        DB::table('playbook_tasks')->where('phase_id', $phaseId)
            ->update(['phase_id' => null]);

        $phase->delete();

        return response()->json(['message' => 'Phase deleted']);
    }

    /**
     * Add a task to a playbook
     */
    public function addTask(Request $request, int $playbookId): JsonResponse
    {
        $playbook = DB::table('playbooks')->where('id', $playbookId)->first();

        $validated = $request->validate([
            'phase_id' => 'nullable|exists:playbook_phases,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'sometimes|string|in:manual,automated,milestone',
            'task_config' => 'nullable|array',
            'due_days' => 'nullable|integer|min:0',
            'duration_estimate' => 'nullable|integer|min:1',
            'is_required' => 'sometimes|boolean',
            'is_milestone' => 'sometimes|boolean',
            'assignee_type' => 'sometimes|string|in:owner,specific_user,role',
            'assignee_id' => 'nullable|exists:users,id',
            'assignee_role' => 'nullable|string',
            'dependencies' => 'nullable|array',
            'dependencies.*' => 'exists:playbook_tasks,id',
            'checklist' => 'nullable|array',
            'resources' => 'nullable|array',
        ]);

        $maxOrder = $playbook->tasks()
            ->where('phase_id', $validated['phase_id'] ?? null)
            ->max('display_order') ?? -1;
        $validated['display_order'] = $maxOrder + 1;

        $task = $playbook->tasks()->create($validated);

        return response()->json([
            'task' => $task->load('phase'),
            'message' => 'Task added successfully',
        ], 201);
    }

    /**
     * Update a task
     */
    public function updateTask(Request $request, int $playbookId, int $taskId): JsonResponse
    {
        $task = DB::table('playbook_tasks')->where('playbook_id', $playbookId)
            ->findOrFail($taskId);

        $validated = $request->validate([
            'phase_id' => 'nullable|exists:playbook_phases,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'task_type' => 'sometimes|string|in:manual,automated,milestone',
            'task_config' => 'nullable|array',
            'due_days' => 'nullable|integer|min:0',
            'duration_estimate' => 'nullable|integer|min:1',
            'is_required' => 'sometimes|boolean',
            'is_milestone' => 'sometimes|boolean',
            'assignee_type' => 'sometimes|string|in:owner,specific_user,role',
            'assignee_id' => 'nullable|exists:users,id',
            'assignee_role' => 'nullable|string',
            'dependencies' => 'nullable|array',
            'checklist' => 'nullable|array',
            'resources' => 'nullable|array',
            'display_order' => 'sometimes|integer',
        ]);

        $task->update($validated);

        return response()->json(['task' => $task->fresh()->load('phase')]);
    }

    /**
     * Delete a task
     */
    public function deleteTask(int $playbookId, int $taskId): JsonResponse
    {
        $task = DB::table('playbook_tasks')->where('playbook_id', $playbookId)
            ->findOrFail($taskId);

        // Remove from other tasks' dependencies
        DB::table('playbook_tasks')->where('playbook_id', $playbookId)
            ->whereJsonContains('dependencies', $taskId)
            ->each(function ($t) use ($taskId) {
                $deps = $t->dependencies ?? [];
                $deps = array_filter($deps, fn($d) => $d !== $taskId);
                $t->dependencies = array_values($deps);
                $t->save();
            });

        $task->delete();

        return response()->json(['message' => 'Task deleted']);
    }

    /**
     * Reorder tasks
     */
    public function reorderTasks(Request $request, int $playbookId): JsonResponse
    {
        $validated = $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|exists:playbook_tasks,id',
            'tasks.*.phase_id' => 'nullable|exists:playbook_phases,id',
            'tasks.*.display_order' => 'required|integer',
        ]);

        foreach ($validated['tasks'] as $taskData) {
            DB::table('playbook_tasks')->where('id', $taskData['id'])
                ->where('playbook_id', $playbookId)
                ->update([
                    'phase_id' => $taskData['phase_id'],
                    'display_order' => $taskData['display_order'],
                ]);
        }

        return response()->json(['message' => 'Tasks reordered']);
    }

    /**
     * Duplicate a playbook
     */
    public function duplicate(int $id): JsonResponse
    {
        $original = Playbook::with(['phases', 'tasks', 'goals'])->findOrFail($id);

        $newPlaybook = $original->replicate();
        $newPlaybook->name = $original->name . ' (Copy)';
        $newPlaybook->slug = Str::slug($newPlaybook->name);
        $newPlaybook->created_by = auth()->id();
        $newPlaybook->save();

        // Duplicate phases
        $phaseMap = [];
        foreach ($original->phases as $phase) {
            $newPhase = $phase->replicate();
            $newPhase->playbook_id = $newPlaybook->id;
            $newPhase->save();
            $phaseMap[$phase->id] = $newPhase->id;
        }

        // Duplicate tasks
        $taskMap = [];
        foreach ($original->tasks as $task) {
            $newTask = $task->replicate();
            $newTask->playbook_id = $newPlaybook->id;
            $newTask->phase_id = $task->phase_id ? ($phaseMap[$task->phase_id] ?? null) : null;
            $newTask->dependencies = null; // Will update after all tasks created
            $newTask->save();
            $taskMap[$task->id] = $newTask->id;
        }

        // Update dependencies with new task IDs
        foreach ($original->tasks as $task) {
            if (!empty($task->dependencies)) {
                $newTask = PlaybookTask::find($taskMap[$task->id]);
                $newTask->dependencies = array_map(fn($d) => $taskMap[$d] ?? $d, $task->dependencies);
                $newTask->save();
            }
        }

        // Duplicate goals
        foreach ($original->goals as $goal) {
            $newGoal = $goal->replicate();
            $newGoal->playbook_id = $newPlaybook->id;
            $newGoal->save();
        }

        return response()->json([
            'playbook' => $newPlaybook->load(['phases', 'tasks', 'goals']),
            'message' => 'Playbook duplicated successfully',
        ], 201);
    }
}
