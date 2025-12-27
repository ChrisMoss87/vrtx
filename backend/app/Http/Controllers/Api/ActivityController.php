<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\Activity\ActivityApplicationService;
use App\Domain\Activity\Repositories\ActivityRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    public function __construct(
        protected ActivityApplicationService $activityApplicationService,
        protected ActivityService $activityService,
        protected ActivityRepositoryInterface $activityRepository
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

        $filters = [];

        if (isset($validated['subject_type']) && isset($validated['subject_id'])) {
            $filters['subject_type'] = $validated['subject_type'];
            $filters['subject_id'] = (int) $validated['subject_id'];
        }

        if (isset($validated['type'])) {
            $filters['type'] = $validated['type'];
        }

        if (isset($validated['user_id'])) {
            $filters['user_id'] = (int) $validated['user_id'];
        }

        if (isset($validated['include_system']) && !$validated['include_system']) {
            $filters['is_system'] = false;
        }

        $perPage = (int) ($validated['per_page'] ?? 25);

        // Handle special filters
        if ($validated['scheduled_only'] ?? false) {
            $activities = $this->activityRepository->findUpcoming(
                isset($validated['user_id']) ? (int) $validated['user_id'] : null
            );
            return response()->json([
                'data' => $activities,
                'meta' => [
                    'total' => count($activities),
                ],
            ]);
        }

        if ($validated['overdue_only'] ?? false) {
            $activities = $this->activityRepository->findOverdue(
                isset($validated['user_id']) ? (int) $validated['user_id'] : null
            );
            return response()->json([
                'data' => $activities,
                'meta' => [
                    'total' => count($activities),
                ],
            ]);
        }

        $result = $this->activityRepository->findWithFilters($filters, $perPage);

        return response()->json([
            'data' => $result->items(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }

    /**
     * Get a single activity.
     */
    public function show(int $id): JsonResponse
    {
        $activity = $this->activityRepository->findByIdWithRelations($id);

        if (!$activity) {
            return response()->json([
                'message' => 'Activity not found',
            ], 404);
        }

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

        $data = [
            'user_id' => Auth::id(),
            'type' => $validated['type'],
            'action' => isset($validated['scheduled_at'])
                ? ActivityRepositoryInterface::ACTION_SCHEDULED
                : ActivityRepositoryInterface::ACTION_CREATED,
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
            'metadata' => isset($validated['metadata']) ? json_encode($validated['metadata']) : null,
            'is_system' => false,
        ];

        $activity = $this->activityRepository->create($data);

        return response()->json([
            'data' => $activity,
            'message' => 'Activity created successfully',
        ], 201);
    }

    /**
     * Update an activity.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $activity = $this->activityRepository->findByIdAsArray($id);

        if (!$activity) {
            return response()->json([
                'message' => 'Activity not found',
            ], 404);
        }

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

        if (isset($validated['metadata'])) {
            $validated['metadata'] = json_encode($validated['metadata']);
        }

        $updated = $this->activityRepository->update($id, $validated);

        return response()->json([
            'data' => $updated,
            'message' => 'Activity updated successfully',
        ]);
    }

    /**
     * Delete an activity.
     */
    public function destroy(int $id): JsonResponse
    {
        $activity = $this->activityRepository->findByIdAsArray($id);

        if (!$activity) {
            return response()->json([
                'message' => 'Activity not found',
            ], 404);
        }

        $this->activityRepository->delete($id);

        return response()->json([
            'message' => 'Activity deleted successfully',
        ]);
    }

    /**
     * Mark activity as completed.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $activity = $this->activityRepository->findByIdAsArray($id);

        if (!$activity) {
            return response()->json([
                'message' => 'Activity not found',
            ], 404);
        }

        $validated = $request->validate([
            'outcome' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
        ]);

        $updated = $this->activityRepository->update($id, [
            'completed_at' => now(),
            'action' => ActivityRepositoryInterface::ACTION_COMPLETED,
            'outcome' => $validated['outcome'] ?? ActivityRepositoryInterface::OUTCOME_COMPLETED,
            'duration_minutes' => $validated['duration_minutes'] ?? $activity['duration_minutes'],
        ]);

        return response()->json([
            'data' => $updated,
            'message' => 'Activity marked as completed',
        ]);
    }

    /**
     * Toggle pinned status.
     */
    public function togglePin(int $id): JsonResponse
    {
        $activity = $this->activityRepository->findByIdAsArray($id);

        if (!$activity) {
            return response()->json([
                'message' => 'Activity not found',
            ], 404);
        }

        $updated = $this->activityRepository->update($id, [
            'is_pinned' => !$activity['is_pinned'],
        ]);

        return response()->json([
            'data' => $updated,
            'message' => $updated['is_pinned'] ? 'Activity pinned' : 'Activity unpinned',
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

        $activities = $this->activityRepository->findForSubject(
            $validated['subject_type'],
            (int) $validated['subject_id'],
            (int) ($validated['limit'] ?? 50),
            $validated['type'] ?? null,
            $validated['include_system'] ?? true
        );

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

        $days = (int) ($validated['days'] ?? 7);
        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : Auth::id();

        $activities = $this->activityRepository->findUpcoming($userId, $days);

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

        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : Auth::id();
        $activities = $this->activityRepository->findOverdue($userId);

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
            'data' => [
                ['value' => ActivityRepositoryInterface::TYPE_NOTE, 'label' => 'Note', 'icon' => 'file-text', 'color' => 'blue'],
                ['value' => ActivityRepositoryInterface::TYPE_CALL, 'label' => 'Call', 'icon' => 'phone', 'color' => 'green'],
                ['value' => ActivityRepositoryInterface::TYPE_MEETING, 'label' => 'Meeting', 'icon' => 'calendar', 'color' => 'purple'],
                ['value' => ActivityRepositoryInterface::TYPE_TASK, 'label' => 'Task', 'icon' => 'check-square', 'color' => 'orange'],
                ['value' => ActivityRepositoryInterface::TYPE_COMMENT, 'label' => 'Comment', 'icon' => 'message-square', 'color' => 'gray'],
                ['value' => ActivityRepositoryInterface::TYPE_EMAIL, 'label' => 'Email', 'icon' => 'mail', 'color' => 'indigo'],
            ],
        ]);
    }

    /**
     * Get outcome types.
     */
    public function outcomes(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['value' => ActivityRepositoryInterface::OUTCOME_COMPLETED, 'label' => 'Completed'],
                ['value' => ActivityRepositoryInterface::OUTCOME_CANCELLED, 'label' => 'Cancelled'],
                ['value' => ActivityRepositoryInterface::OUTCOME_NO_ANSWER, 'label' => 'No Answer'],
                ['value' => ActivityRepositoryInterface::OUTCOME_LEFT_VOICEMAIL, 'label' => 'Left Voicemail'],
                ['value' => ActivityRepositoryInterface::OUTCOME_BUSY, 'label' => 'Busy'],
                ['value' => ActivityRepositoryInterface::OUTCOME_RESCHEDULED, 'label' => 'Rescheduled'],
            ],
        ]);
    }
}
