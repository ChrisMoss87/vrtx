<?php

namespace App\Http\Controllers\Api\Video;

use App\Http\Controllers\Controller;
use App\Models\VideoMeeting;
use App\Models\VideoMeetingRecording;
use App\Services\Video\VideoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VideoRecordingController extends Controller
{
    public function __construct(
        protected VideoService $videoService
    ) {}

    public function index(VideoMeeting $videoMeeting): JsonResponse
    {
        $recordings = $videoMeeting->recordings()
            ->orderBy('recording_start', 'desc')
            ->get();

        return response()->json([
            'data' => $recordings,
        ]);
    }

    public function show(VideoMeeting $videoMeeting, VideoMeetingRecording $recording): JsonResponse
    {
        // Verify recording belongs to this meeting
        if ($recording->meeting_id !== $videoMeeting->id) {
            return response()->json([
                'message' => 'Recording not found in this meeting',
            ], 404);
        }

        return response()->json([
            'data' => $recording,
        ]);
    }

    public function destroy(Request $request, VideoMeeting $videoMeeting, VideoMeetingRecording $recording): JsonResponse
    {
        // Only host can delete recordings
        if ($videoMeeting->host_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Only the meeting host can delete recordings',
            ], 403);
        }

        // Verify recording belongs to this meeting
        if ($recording->meeting_id !== $videoMeeting->id) {
            return response()->json([
                'message' => 'Recording not found in this meeting',
            ], 404);
        }

        try {
            $this->videoService->deleteRecording($recording);

            return response()->json([
                'message' => 'Recording deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete recording: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function getTranscript(VideoMeeting $videoMeeting, VideoMeetingRecording $recording): JsonResponse
    {
        // Verify recording belongs to this meeting
        if ($recording->meeting_id !== $videoMeeting->id) {
            return response()->json([
                'message' => 'Recording not found in this meeting',
            ], 404);
        }

        if ($recording->type !== 'transcript' && !$recording->transcript_text) {
            return response()->json([
                'message' => 'No transcript available for this recording',
            ], 404);
        }

        return response()->json([
            'data' => [
                'text' => $recording->transcript_text,
                'segments' => $recording->transcript_segments,
            ],
        ]);
    }

    public function listAll(Request $request): JsonResponse
    {
        $query = VideoMeetingRecording::with(['meeting.host'])
            ->whereHas('meeting', function ($q) use ($request) {
                // Filter to meetings user has access to
                $user = $request->user();
                $q->where('host_id', $user->id)
                    ->orWhereHas('participants', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id)
                            ->orWhere('email', $user->email);
                    });
            });

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by meeting title
        if ($request->has('search')) {
            $query->whereHas('meeting', function ($q) use ($request) {
                $q->where('title', 'ilike', '%' . $request->search . '%');
            });
        }

        $recordings = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($recordings);
    }
}
