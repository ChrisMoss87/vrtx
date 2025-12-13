<?php

namespace App\Http\Controllers\Api\Video;

use App\Http\Controllers\Controller;
use App\Models\VideoMeeting;
use App\Models\VideoMeetingParticipant;
use App\Services\Video\VideoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VideoParticipantController extends Controller
{
    public function __construct(
        protected VideoService $videoService
    ) {}

    public function index(VideoMeeting $videoMeeting): JsonResponse
    {
        $participants = $videoMeeting->participants()
            ->with('user')
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $participants,
        ]);
    }

    public function store(Request $request, VideoMeeting $videoMeeting): JsonResponse
    {
        // Only host can add participants
        if ($videoMeeting->host_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only the meeting host can add participants',
            ], 403);
        }

        if (in_array($videoMeeting->status, ['ended', 'canceled'])) {
            return response()->json([
                'message' => 'Cannot add participants to ended or canceled meetings',
            ], 422);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'role' => 'nullable|string|in:attendee,co-host',
        ]);

        try {
            $participant = $this->videoService->addParticipant($videoMeeting, $validated);

            return response()->json([
                'data' => $participant->fresh('user'),
                'message' => 'Participant added successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add participant: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Request $request, VideoMeeting $videoMeeting, VideoMeetingParticipant $participant): JsonResponse
    {
        // Only host can remove participants
        if ($videoMeeting->host_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only the meeting host can remove participants',
            ], 403);
        }

        // Verify participant belongs to this meeting
        if ($participant->meeting_id !== $videoMeeting->id) {
            return response()->json([
                'message' => 'Participant not found in this meeting',
            ], 404);
        }

        try {
            $this->videoService->removeParticipant($videoMeeting, $participant);

            return response()->json([
                'message' => 'Participant removed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove participant: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function bulkAdd(Request $request, VideoMeeting $videoMeeting): JsonResponse
    {
        // Only host can add participants
        if ($videoMeeting->host_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only the meeting host can add participants',
            ], 403);
        }

        $validated = $request->validate([
            'participants' => 'required|array|min:1',
            'participants.*.email' => 'required|email',
            'participants.*.name' => 'nullable|string|max:255',
            'participants.*.role' => 'nullable|string|in:attendee,co-host',
        ]);

        $added = [];
        $failed = [];

        foreach ($validated['participants'] as $participantData) {
            try {
                $participant = $this->videoService->addParticipant($videoMeeting, $participantData);
                $added[] = $participant;
            } catch (\Exception $e) {
                $failed[] = [
                    'email' => $participantData['email'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'data' => [
                'added' => $added,
                'failed' => $failed,
            ],
            'message' => count($added) . ' participant(s) added' .
                (count($failed) > 0 ? ', ' . count($failed) . ' failed' : ''),
        ]);
    }
}
