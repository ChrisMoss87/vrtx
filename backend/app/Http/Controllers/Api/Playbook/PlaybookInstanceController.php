<?php

namespace App\Http\Controllers\Api\Playbook;

use App\Http\Controllers\Controller;
use App\Models\Playbook;
use App\Models\PlaybookInstance;
use App\Models\PlaybookTaskInstance;
use App\Services\Playbook\PlaybookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaybookInstanceController extends Controller
{
    public function __construct(
        protected PlaybookService $playbookService
    ) {}

    /**
     * List playbook instances
     */
    public function index(Request $request): JsonResponse
    {
        $query = PlaybookInstance::with([
            'playbook',
            'owner',
            'taskInstances.task',
        ]);

        if ($request->has('playbook_id')) {
            $query->where('playbook_id', $request->input('playbook_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }

        if ($request->has('related_module') && $request->has('related_id')) {
            $query->forRecord(
                $request->input('related_module'),
                $request->input('related_id')
            );
        }

        $instances = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($instances);
    }

    /**
     * Get a single instance
     */
    public function show(int $id): JsonResponse
    {
        $instance = PlaybookInstance::with([
            'playbook.phases',
            'owner',
            'taskInstances.task.phase',
            'taskInstances.assignee',
            'activities.user',
            'goalResults.goal',
        ])->findOrFail($id);

        return response()->json(['instance' => $instance]);
    }

    /**
     * Start a playbook for a record
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'playbook_id' => 'required|exists:playbooks,id',
            'related_module' => 'required|string',
            'related_id' => 'required|integer',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $playbook = Playbook::findOrFail($validated['playbook_id']);

        // Check for existing active instance
        $existing = PlaybookInstance::where('playbook_id', $validated['playbook_id'])
            ->forRecord($validated['related_module'], $validated['related_id'])
            ->active()
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'This playbook is already active for this record',
                'instance' => $existing,
            ], 422);
        }

        $instance = $this->playbookService->startPlaybook(
            $playbook,
            $validated['related_module'],
            $validated['related_id'],
            $validated['owner_id'] ?? null
        );

        return response()->json([
            'instance' => $instance->load(['playbook', 'taskInstances.task', 'owner']),
            'message' => 'Playbook started successfully',
        ], 201);
    }

    /**
     * Pause an instance
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $instance = PlaybookInstance::findOrFail($id);

        if ($instance->status !== 'active') {
            return response()->json([
                'message' => 'Can only pause active playbooks',
            ], 422);
        }

        $this->playbookService->pausePlaybook(
            $instance,
            $request->input('reason')
        );

        return response()->json([
            'instance' => $instance->fresh(),
            'message' => 'Playbook paused',
        ]);
    }

    /**
     * Resume an instance
     */
    public function resume(int $id): JsonResponse
    {
        $instance = PlaybookInstance::findOrFail($id);

        if ($instance->status !== 'paused') {
            return response()->json([
                'message' => 'Can only resume paused playbooks',
            ], 422);
        }

        $this->playbookService->resumePlaybook($instance);

        return response()->json([
            'instance' => $instance->fresh(),
            'message' => 'Playbook resumed',
        ]);
    }

    /**
     * Cancel an instance
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $instance = PlaybookInstance::findOrFail($id);

        if (!in_array($instance->status, ['active', 'paused'])) {
            return response()->json([
                'message' => 'Cannot cancel this playbook',
            ], 422);
        }

        $this->playbookService->cancelPlaybook(
            $instance,
            $request->input('reason')
        );

        return response()->json([
            'instance' => $instance->fresh(),
            'message' => 'Playbook cancelled',
        ]);
    }

    /**
     * Get tasks for an instance
     */
    public function tasks(int $id): JsonResponse
    {
        $instance = PlaybookInstance::findOrFail($id);

        $tasks = $instance->taskInstances()
            ->with(['task.phase', 'assignee'])
            ->get()
            ->groupBy(fn($t) => $t->task->phase_id ?? 'uncategorized');

        return response()->json(['tasks' => $tasks]);
    }

    /**
     * Start a task
     */
    public function startTask(int $instanceId, int $taskInstanceId): JsonResponse
    {
        $taskInstance = PlaybookTaskInstance::where('instance_id', $instanceId)
            ->findOrFail($taskInstanceId);

        if ($taskInstance->status !== 'pending') {
            return response()->json([
                'message' => 'Task is not pending',
            ], 422);
        }

        try {
            $this->playbookService->startTask($taskInstance);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'task' => $taskInstance->fresh()->load(['task', 'assignee']),
            'message' => 'Task started',
        ]);
    }

    /**
     * Complete a task
     */
    public function completeTask(Request $request, int $instanceId, int $taskInstanceId): JsonResponse
    {
        $taskInstance = PlaybookTaskInstance::where('instance_id', $instanceId)
            ->findOrFail($taskInstanceId);

        if (!in_array($taskInstance->status, ['pending', 'in_progress'])) {
            return response()->json([
                'message' => 'Task cannot be completed',
            ], 422);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
            'time_spent' => 'nullable|integer|min:0',
        ]);

        $this->playbookService->completeTask(
            $taskInstance,
            $validated['notes'] ?? null,
            $validated['time_spent'] ?? null
        );

        return response()->json([
            'task' => $taskInstance->fresh()->load(['task', 'assignee']),
            'instance' => $taskInstance->instance->fresh(),
            'message' => 'Task completed',
        ]);
    }

    /**
     * Skip a task
     */
    public function skipTask(Request $request, int $instanceId, int $taskInstanceId): JsonResponse
    {
        $taskInstance = PlaybookTaskInstance::where('instance_id', $instanceId)
            ->findOrFail($taskInstanceId);

        // Check if task is required
        if ($taskInstance->task->is_required) {
            return response()->json([
                'message' => 'Cannot skip required tasks',
            ], 422);
        }

        $this->playbookService->skipTask(
            $taskInstance,
            $request->input('reason')
        );

        return response()->json([
            'task' => $taskInstance->fresh()->load(['task', 'assignee']),
            'instance' => $taskInstance->instance->fresh(),
            'message' => 'Task skipped',
        ]);
    }

    /**
     * Reassign a task
     */
    public function reassignTask(Request $request, int $instanceId, int $taskInstanceId): JsonResponse
    {
        $taskInstance = PlaybookTaskInstance::where('instance_id', $instanceId)
            ->findOrFail($taskInstanceId);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->playbookService->reassignTask($taskInstance, $validated['user_id']);

        return response()->json([
            'task' => $taskInstance->fresh()->load(['task', 'assignee']),
            'message' => 'Task reassigned',
        ]);
    }

    /**
     * Update task checklist
     */
    public function updateTaskChecklist(Request $request, int $instanceId, int $taskInstanceId): JsonResponse
    {
        $taskInstance = PlaybookTaskInstance::where('instance_id', $instanceId)
            ->findOrFail($taskInstanceId);

        $validated = $request->validate([
            'index' => 'required|integer|min:0',
            'completed' => 'required|boolean',
        ]);

        $taskInstance->updateChecklistItem($validated['index'], $validated['completed']);

        return response()->json([
            'task' => $taskInstance->fresh(),
            'checklist_progress' => $taskInstance->getChecklistProgress(),
        ]);
    }

    /**
     * Get activity log for an instance
     */
    public function activities(int $id): JsonResponse
    {
        $instance = PlaybookInstance::findOrFail($id);

        $activities = $instance->activities()
            ->with(['user', 'taskInstance.task'])
            ->paginate(50);

        return response()->json($activities);
    }

    /**
     * Get playbooks for a specific record
     */
    public function forRecord(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module' => 'required|string',
            'record_id' => 'required|integer',
        ]);

        $instances = PlaybookInstance::with(['playbook', 'owner'])
            ->forRecord($validated['module'], $validated['record_id'])
            ->orderByDesc('created_at')
            ->get();

        $available = Playbook::active()
            ->forModule($validated['module'])
            ->whereDoesntHave('instances', function ($q) use ($validated) {
                $q->forRecord($validated['module'], $validated['record_id'])
                    ->active();
            })
            ->get();

        return response()->json([
            'active_instances' => $instances->where('status', 'active'),
            'past_instances' => $instances->whereIn('status', ['completed', 'cancelled']),
            'available_playbooks' => $available,
        ]);
    }

    /**
     * Get my tasks across all playbooks
     */
    public function myTasks(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $overdue = $this->playbookService->getOverdueTasksForUser($userId);
        $upcoming = $this->playbookService->getUpcomingTasksForUser(
            $userId,
            $request->input('days', 7)
        );

        $inProgress = PlaybookTaskInstance::with(['instance.playbook', 'task'])
            ->where('assigned_to', $userId)
            ->where('status', 'in_progress')
            ->get();

        return response()->json([
            'overdue' => $overdue,
            'in_progress' => $inProgress,
            'upcoming' => $upcoming,
        ]);
    }
}
