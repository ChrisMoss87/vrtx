<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\Activity\ActivityApplicationService;
use App\Http\Controllers\Controller;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    public function __construct(
        protected ActivityApplicationService $activityApplicationService,
        protected ActivityService $activityService
    ) {}

    /**
     * List activities with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_type' => 'nullable|string',
            'subject_id' => 'nullable|integer',
            'type' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'include_system' => 'nullable|boolean',
            'scheduled_only' => 'nullable|boolean',
            'overdue_only' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DB::table('activities')
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if (isset($validated['subject_type']) && isset($validated['subject_id'])) {
            $query->forSubject($validated['subject_type'], $validated['subject_id']);
        }

        if (isset($validated['type'])) {
            $query->ofType($validated['type']);
        }

        if (isset($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (isset($validated['include_system']) && !$validated['include_system']) {
            $query->userActivities();
        }

        if ($validated['scheduled_only'] ?? false) {
            $query->upcoming();
        }

        if ($validated['overdue_only'] ?? false) {
            $query->overdue();
        }

        $perPage = $validated['per_page'] ?? 25;
        $activities = $query->paginate($perPage);

        return response()->json([
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Get a single activity.
     */
    public function show(Activity $activity): JsonResponse
    {
        $activity->load(['user:id,name,email', 'related']);

        return response()->json([
            'data' => $activity,
        ]);
    }

    /**
     * Create a new activity.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:note,call,meeting,task,comment',
            'subject_type' => 'required|string',
            'subject_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'outcome' => 'nullable|string',
            'is_internal' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $activity = DB::table('activities')->insertGetId([
            'user_id' => Auth::id(),
            'type' => $validated['type'],
            'action' => isset($validated['scheduled_at']) ? Activity::ACTION_SCHEDULED : Activity::ACTION_CREATED,
            'subject_type' => $validated['subject_type'],
            'subject_id' => $validated['subject_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'outcome' => $validated['outcome'] ?? null,
            'is_internal' => $validated['is_internal'] ?? false,
            'is_pinned' => $validated['is_pinned'] ?? false,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        $activity->load('user:id,name,email');

        return response()->json([
            'data' => $activity,
            'message' => 'Activity created successfully',
        ], 201);
    }

    /**
     * Update an activity.
     */
    public function update(Request $request, Activity $activity): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'outcome' => 'nullable|string',
            'is_internal' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $activity->update($validated);
        $activity->load('user:id,name,email');

        return response()->json([
            'data' => $activity,
            'message' => 'Activity updated successfully',
        ]);
    }

    /**
     * Delete an activity.
     */
    public function destroy(Activity $activity): JsonResponse
    {
        $activity->delete();

        return response()->json([
            'message' => 'Activity deleted successfully',
        ]);
    }

    /**
     * Mark activity as completed.
     */
    public function complete(Request $request, Activity $activity): JsonResponse
    {
        $validated = $request->validate([
            'outcome' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
        ]);

        $activity->update([
            'completed_at' => now(),
            'action' => Activity::ACTION_COMPLETED,
            'outcome' => $validated['outcome'] ?? Activity::OUTCOME_COMPLETED,
            'duration_minutes' => $validated['duration_minutes'] ?? $activity->duration_minutes,
        ]);

        $activity->load('user:id,name,email');

        return response()->json([
            'data' => $activity,
            'message' => 'Activity marked as completed',
        ]);
    }

    /**
     * Toggle pinned status.
     */
    public function togglePin(Activity $activity): JsonResponse
    {
        $activity->togglePin();

        return response()->json([
            'data' => $activity->fresh(),
            'message' => $activity->is_pinned ? 'Activity pinned' : 'Activity unpinned',
        ]);
    }

    /**
     * Get timeline for a record.
     */
    public function timeline(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_type' => 'required|string',
            'subject_id' => 'required|integer',
            'type' => 'nullable|string',
            'include_system' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Activity::forSubject($validated['subject_type'], $validated['subject_id'])
            ->with('user:id,name,email')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc');

        if (isset($validated['type'])) {
            $query->ofType($validated['type']);
        }

        if (isset($validated['include_system']) && !$validated['include_system']) {
            $query->userActivities();
        }

        $limit = $validated['limit'] ?? 50;
        $activities = $query->limit($limit)->get();

        return response()->json([
            'data' => $activities,
        ]);
    }

    /**
     * Get upcoming activities.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:90',
            'user_id' => 'nullable|integer',
        ]);

        $days = $validated['days'] ?? 7;
        $userId = $validated['user_id'] ?? Auth::id();

        $activities = $this->activityService->getUpcoming($userId, $days);

        return response()->json([
            'data' => $activities,
        ]);
    }

    /**
     * Get overdue activities.
     */
    public function overdue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer',
        ]);

        $userId = $validated['user_id'] ?? Auth::id();
        $activities = $this->activityService->getOverdue($userId);

        return response()->json([
            'data' => $activities,
        ]);
    }

    /**
     * Get activity types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => Activity::getTypes(),
        ]);
    }

    /**
     * Get outcome types.
     */
    public function outcomes(): JsonResponse
    {
        return response()->json([
            'data' => Activity::getOutcomes(),
        ]);
    }
}
