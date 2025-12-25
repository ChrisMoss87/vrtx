<?php

namespace App\Http\Controllers\Api\Video;

use App\Application\Services\Video\VideoApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Video\VideoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VideoMeetingController extends Controller
{
    public function __construct(
        protected VideoApplicationService $videoApplicationService,
        protected VideoService $videoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = VideoMeeting::with(['provider', 'host', 'participants']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by host
        if ($request->has('host_id')) {
            $query->where('host_id', $request->host_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('scheduled_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('scheduled_at', '<=', $request->end_date);
        }

        // Filter for current user's meetings
        if ($request->boolean('my_meetings')) {
            $user = $request->user();
            $query->where(function ($q) use ($user) {
                $q->where('host_id', $user->id)
                    ->orWhereHas('participants', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id)
                            ->orWhere('email', $user->email);
                    });
            });
        }

        // Filter by deal
        if ($request->has('deal_id') && $request->has('deal_module')) {
            $query->where('deal_id', $request->deal_id)
                ->where('deal_module', $request->deal_module);
        }

        $meetings = $query->orderBy('scheduled_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($meetings);
    }

    public function show(VideoMeeting $videoMeeting): JsonResponse
    {
        $videoMeeting->load(['provider', 'host', 'participants.user', 'recordings']);

        return response()->json([
            'data' => $videoMeeting,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => 'required|exists:video_providers,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'waiting_room_enabled' => 'nullable|boolean',
            'recording_enabled' => 'nullable|boolean',
            'recording_auto_start' => 'nullable|boolean',
            'meeting_type' => 'nullable|string|in:instant,scheduled,recurring',
            'recurrence_type' => 'nullable|string|in:daily,weekly,monthly',
            'recurrence_settings' => 'nullable|array',
            'deal_id' => 'nullable|integer',
            'deal_module' => 'nullable|string',
            'participants' => 'nullable|array',
            'participants.*.email' => 'required_with:participants|email',
            'participants.*.name' => 'nullable|string',
            'participants.*.role' => 'nullable|string|in:attendee,co-host',
            'custom_fields' => 'nullable|array',
        ]);

        $provider = VideoProvider::findOrFail($validated['provider_id']);

        if (!$provider->is_active) {
            return response()->json([
                'message' => 'Selected video provider is not active',
            ], 422);
        }

        try {
            $meeting = $this->videoService->createMeeting(
                $provider,
                $request->user(),
                $validated
            );

            return response()->json([
                'data' => $meeting,
                'message' => 'Meeting created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create meeting: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, VideoMeeting $videoMeeting): JsonResponse
    {
        // Only host can update
        if ($videoMeeting->host_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only the meeting host can update this meeting',
            ], 403);
        }

        // Cannot update ended or canceled meetings
        if (in_array($videoMeeting->status, ['ended', 'canceled'])) {
            return response()->json([
                'message' => 'Cannot update a meeting that has ended or been canceled',
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'sometimes|date|after:now',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'waiting_room_enabled' => 'nullable|boolean',
            'recording_enabled' => 'nullable|boolean',
            'recording_auto_start' => 'nullable|boolean',
        ]);

        try {
            $meeting = $this->videoService->updateMeeting($videoMeeting, $validated);

            return response()->json([
                'data' => $meeting,
                'message' => 'Meeting updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update meeting: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(Request $request, VideoMeeting $videoMeeting): JsonResponse
    {
        // Only host can cancel
        if ($videoMeeting->host_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only the meeting host can cancel this meeting',
            ], 403);
        }

        if ($videoMeeting->status !== 'scheduled') {
            return response()->json([
                'message' => 'Can only cancel scheduled meetings',
            ], 422);
        }

        try {
            $meeting = $this->videoService->cancelMeeting($videoMeeting);

            return response()->json([
                'data' => $meeting,
                'message' => 'Meeting canceled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel meeting: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function end(Request $request, VideoMeeting $videoMeeting): JsonResponse
    {
        // Only host can end
        if ($videoMeeting->host_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only the meeting host can end this meeting',
            ], 403);
        }

        if (!in_array($videoMeeting->status, ['scheduled', 'started'])) {
            return response()->json([
                'message' => 'Meeting cannot be ended in current state',
            ], 422);
        }

        try {
            $meeting = $this->videoService->endMeeting($videoMeeting);

            return response()->json([
                'data' => $meeting,
                'message' => 'Meeting ended successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to end meeting: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function upcoming(Request $request): JsonResponse
    {
        $meetings = $this->videoService->getUpcomingMeetings(
            $request->user(),
            $request->input('limit', 10)
        );

        return response()->json([
            'data' => $meetings,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = $this->videoService->getMeetingStats(
            $request->user(),
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json([
            'data' => $stats,
        ]);
    }

    public function syncRecordings(VideoMeeting $videoMeeting): JsonResponse
    {
        try {
            $this->videoService->syncRecordings($videoMeeting);

            return response()->json([
                'data' => $videoMeeting->fresh(['recordings']),
                'message' => 'Recordings synced successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to sync recordings: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function syncParticipants(VideoMeeting $videoMeeting): JsonResponse
    {
        try {
            $this->videoService->syncParticipants($videoMeeting);

            return response()->json([
                'data' => $videoMeeting->fresh(['participants']),
                'message' => 'Participants synced successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to sync participants: ' . $e->getMessage(),
            ], 422);
        }
    }
}
