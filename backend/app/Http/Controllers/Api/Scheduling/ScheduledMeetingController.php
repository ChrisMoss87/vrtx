<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Application\Services\Scheduling\SchedulingApplicationService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScheduledMeetingController extends Controller
{
    public function __construct(
        protected SchedulingApplicationService $schedulingService
    ) {}

    /**
     * List scheduled meetings for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:scheduled,completed,cancelled,no_show',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'upcoming_only' => 'nullable|boolean',
        ]);

        $query = DB::table('scheduled_meetings')->where('host_user_id', Auth::id())
            ->with(['meetingType.schedulingPage', 'contact']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->start_date) {
            $query->where('start_time', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('start_time', '<=', $request->end_date);
        }

        if ($request->boolean('upcoming_only')) {
            $query->upcoming();
        }

        $meetings = $query->orderBy('start_time')->paginate(20);

        return response()->json([
            'meetings' => $meetings,
        ]);
    }

    /**
     * Get a specific meeting.
     */
    public function show(ScheduledMeeting $scheduledMeeting): JsonResponse
    {
        if ($scheduledMeeting->host_user_id !== Auth::id()) {
            abort(403);
        }

        return response()->json([
            'meeting' => $scheduledMeeting->load(['meetingType.schedulingPage', 'contact', 'host']),
        ]);
    }

    /**
     * Update a meeting (by host).
     */
    public function update(Request $request, ScheduledMeeting $scheduledMeeting): JsonResponse
    {
        if ($scheduledMeeting->host_user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:scheduled,completed,no_show',
            'location' => 'nullable|string',
        ]);

        $scheduledMeeting->update($validated);

        return response()->json([
            'message' => 'Meeting updated successfully',
            'meeting' => $scheduledMeeting->fresh(),
        ]);
    }

    /**
     * Cancel a meeting (by host).
     */
    public function cancel(Request $request, ScheduledMeeting $scheduledMeeting): JsonResponse
    {
        if ($scheduledMeeting->host_user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->schedulingService->cancelMeeting(
            $scheduledMeeting->id,
            $request->reason,
            Auth::id()
        );

        return response()->json([
            'message' => 'Meeting cancelled successfully',
        ]);
    }

    /**
     * Mark a meeting as completed.
     */
    public function markCompleted(ScheduledMeeting $scheduledMeeting): JsonResponse
    {
        if ($scheduledMeeting->host_user_id !== Auth::id()) {
            abort(403);
        }

        $scheduledMeeting->markCompleted();

        return response()->json([
            'message' => 'Meeting marked as completed',
            'meeting' => $scheduledMeeting->fresh(),
        ]);
    }

    /**
     * Mark a meeting as no-show.
     */
    public function markNoShow(ScheduledMeeting $scheduledMeeting): JsonResponse
    {
        if ($scheduledMeeting->host_user_id !== Auth::id()) {
            abort(403);
        }

        $scheduledMeeting->markNoShow();

        return response()->json([
            'message' => 'Meeting marked as no-show',
            'meeting' => $scheduledMeeting->fresh(),
        ]);
    }

    /**
     * Get meeting statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now()->endOfMonth();

        $query = DB::table('scheduled_meetings')->where('host_user_id', Auth::id())
            ->whereBetween('start_time', [$startDate, $endDate]);

        $stats = [
            'total' => (clone $query)->count(),
            'scheduled' => (clone $query)->where('status', 'scheduled')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'no_show' => (clone $query)->where('status', 'no_show')->count(),
            'upcoming' => DB::table('scheduled_meetings')->where('host_user_id', Auth::id())
                ->where('status', 'scheduled')
                ->where('start_time', '>', now())
                ->count(),
            'upcoming_week' => DB::table('scheduled_meetings')->where('host_user_id', Auth::id())
                ->where('status', 'scheduled')
                ->whereBetween('start_time', [now(), now()->addWeek()])
                ->count(),
        ];

        // Calculate show rate
        $totalAttended = $stats['completed'] + $stats['no_show'];
        $stats['show_rate'] = $totalAttended > 0
            ? round(($stats['completed'] / $totalAttended) * 100, 1)
            : null;

        return response()->json([
            'stats' => $stats,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ]);
    }
}
