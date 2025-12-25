<?php

namespace App\Http\Controllers\Api\Meeting;

use App\Http\Controllers\Controller;
use App\Services\Meeting\MeetingService;
use App\Services\Meeting\MeetingAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingController extends Controller
{
    public function __construct(
        private MeetingService $meetingService,
        private MeetingAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $dealId = $request->query('deal_id');
        $companyId = $request->query('company_id');

        $meetings = $this->meetingService->getMeetings(
            $request->user(),
            $from,
            $to,
            $dealId ? (int) $dealId : null,
            $companyId ? (int) $companyId : null
        );

        return response()->json([
            'data' => $meetings->map(fn ($m) => $this->formatMeeting($m)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $meeting = $this->meetingService->getMeeting($id);

        if (!$meeting) {
            return response()->json(['error' => 'Meeting not found'], 404);
        }

        return response()->json([
            'data' => $this->formatMeeting($meeting, true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'location' => 'nullable|string|max:500',
            'is_online' => 'sometimes|boolean',
            'meeting_url' => 'nullable|url|max:500',
            'deal_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'participants' => 'nullable|array',
            'participants.*.email' => 'required|email',
            'participants.*.name' => 'nullable|string|max:255',
        ]);

        $meeting = $this->meetingService->createMeeting($request->user(), $validated);

        return response()->json([
            'data' => $this->formatMeeting($meeting, true),
            'message' => 'Meeting created',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $meeting = DB::table('synced_meetings')->where('id', $id)->first();

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'description' => 'nullable|string',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date',
            'location' => 'nullable|string|max:500',
            'is_online' => 'sometimes|boolean',
            'meeting_url' => 'nullable|url|max:500',
            'deal_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
        ]);

        $meeting = $this->meetingService->updateMeeting($meeting, $validated);

        return response()->json([
            'data' => $this->formatMeeting($meeting),
            'message' => 'Meeting updated',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $meeting = DB::table('synced_meetings')->where('id', $id)->first();
        $this->meetingService->deleteMeeting($meeting);

        return response()->json(['message' => 'Meeting deleted']);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 10);
        $meetings = $this->meetingService->getUpcomingMeetings($request->user(), (int) $limit);

        return response()->json([
            'data' => $meetings->map(fn ($m) => $this->formatMeeting($m)),
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $meetings = $this->meetingService->getTodaysMeetings($request->user());

        return response()->json([
            'data' => $meetings->map(fn ($m) => $this->formatMeeting($m)),
        ]);
    }

    public function linkToDeal(Request $request, int $id): JsonResponse
    {
        $meeting = DB::table('synced_meetings')->where('id', $id)->first();

        $validated = $request->validate([
            'deal_id' => 'required|integer',
        ]);

        $meeting = $this->meetingService->linkMeetingToDeal($meeting, $validated['deal_id']);

        return response()->json([
            'data' => $this->formatMeeting($meeting),
            'message' => 'Meeting linked to deal',
        ]);
    }

    public function logOutcome(Request $request, int $id): JsonResponse
    {
        $meeting = DB::table('synced_meetings')->where('id', $id)->first();

        $validated = $request->validate([
            'outcome' => 'required|string|in:completed,no_show,rescheduled,cancelled',
            'notes' => 'nullable|string|max:1000',
        ]);

        $meeting = $this->meetingService->recordOutcome(
            $meeting,
            $validated['outcome'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'data' => $this->formatMeeting($meeting),
            'message' => 'Outcome recorded',
        ]);
    }

    // Analytics endpoints
    public function analyticsOverview(Request $request): JsonResponse
    {
        $period = $request->query('period', 'month');
        $overview = $this->analyticsService->getOverview($request->user(), $period);

        return response()->json(['data' => $overview]);
    }

    public function analyticsHeatmap(Request $request): JsonResponse
    {
        $weeks = $request->query('weeks', 4);
        $heatmap = $this->analyticsService->getHeatmap($request->user(), (int) $weeks);

        return response()->json(['data' => $heatmap]);
    }

    public function analyticsByDeal(int $dealId): JsonResponse
    {
        $analytics = $this->analyticsService->getDealAnalytics($dealId);

        return response()->json(['data' => $analytics]);
    }

    public function analyticsByCompany(int $companyId): JsonResponse
    {
        $coverage = $this->analyticsService->getStakeholderCoverage($companyId);

        return response()->json(['data' => $coverage]);
    }

    public function stakeholderCoverage(Request $request, int $companyId): JsonResponse
    {
        $dealId = $request->query('deal_id');
        $coverage = $this->analyticsService->getStakeholderCoverage($companyId, $dealId ? (int) $dealId : null);

        return response()->json(['data' => $coverage]);
    }

    public function dealInsights(int $dealId): JsonResponse
    {
        $insights = $this->analyticsService->getDealInsights($dealId);

        return response()->json(['data' => $insights]);
    }

    private function formatMeeting(SyncedMeeting $meeting, bool $includeParticipants = false): array
    {
        $data = [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'description' => $meeting->description,
            'start_time' => $meeting->start_time->toISOString(),
            'end_time' => $meeting->end_time->toISOString(),
            'duration_minutes' => $meeting->getDurationMinutes(),
            'location' => $meeting->location,
            'is_online' => $meeting->is_online,
            'meeting_url' => $meeting->meeting_url,
            'status' => $meeting->status,
            'outcome' => $meeting->outcome,
            'outcome_notes' => $meeting->outcome_notes,
            'deal_id' => $meeting->deal_id,
            'company_id' => $meeting->company_id,
            'is_upcoming' => $meeting->isUpcoming(),
            'is_today' => $meeting->isToday(),
            'participant_count' => $meeting->participants->count(),
            'calendar_provider' => $meeting->calendar_provider,
        ];

        if ($includeParticipants) {
            $data['participants'] = $meeting->participants->map(fn ($p) => [
                'id' => $p->id,
                'email' => $p->email,
                'name' => $p->name,
                'contact_id' => $p->contact_id,
                'is_organizer' => $p->is_organizer,
                'response_status' => $p->response_status,
            ]);
        }

        return $data;
    }
}
