<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Application\Services\Scheduling\SchedulingApplicationService;
use App\Domain\Scheduling\DTOs\CreateMeetingDTO;
use App\Http\Controllers\Controller;
use App\Models\MeetingType;
use App\Models\ScheduledMeeting;
use App\Models\SchedulingPage;
use App\Services\Scheduling\AvailabilityService;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected SchedulingApplicationService $schedulingService
    ) {}

    /**
     * Get public scheduling page details.
     */
    public function getPage(string $slug): JsonResponse
    {
        $page = SchedulingPage::where('slug', $slug)
            ->where('is_active', true)
            ->with(['user:id,name,email', 'activeMeetingTypes'])
            ->first();

        if (!$page) {
            return response()->json([
                'message' => 'Scheduling page not found',
            ], 404);
        }

        return response()->json([
            'page' => [
                'name' => $page->name,
                'slug' => $page->slug,
                'description' => $page->description,
                'timezone' => $page->timezone,
                'branding' => $page->branding,
                'host' => [
                    'name' => $page->user->name,
                ],
            ],
            'meeting_types' => $page->activeMeetingTypes->map(fn($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'duration_minutes' => $type->duration_minutes,
                'description' => $type->description,
                'location_type' => $type->location_type,
                'color' => $type->color,
            ]),
        ]);
    }

    /**
     * Get meeting type details.
     */
    public function getMeetingType(string $pageSlug, string $typeSlug): JsonResponse
    {
        $page = SchedulingPage::where('slug', $pageSlug)
            ->where('is_active', true)
            ->first();

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $meetingType = MeetingType::where('scheduling_page_id', $page->id)
            ->where('slug', $typeSlug)
            ->where('is_active', true)
            ->first();

        if (!$meetingType) {
            return response()->json(['message' => 'Meeting type not found'], 404);
        }

        return response()->json([
            'page' => [
                'name' => $page->name,
                'slug' => $page->slug,
                'timezone' => $page->timezone,
                'branding' => $page->branding,
                'host' => [
                    'name' => $page->user->name,
                ],
            ],
            'meeting_type' => [
                'id' => $meetingType->id,
                'name' => $meetingType->name,
                'slug' => $meetingType->slug,
                'duration_minutes' => $meetingType->duration_minutes,
                'description' => $meetingType->description,
                'location_type' => $meetingType->location_type,
                'color' => $meetingType->color,
                'questions' => $meetingType->questions,
                'settings' => [
                    'max_days_advance' => $meetingType->max_days_advance,
                ],
            ],
        ]);
    }

    /**
     * Get available dates for a meeting type.
     */
    public function getAvailableDates(Request $request, string $pageSlug, string $typeSlug): JsonResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
            'timezone' => 'required|timezone',
        ]);

        $page = SchedulingPage::where('slug', $pageSlug)
            ->where('is_active', true)
            ->first();

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $meetingType = MeetingType::where('scheduling_page_id', $page->id)
            ->where('slug', $typeSlug)
            ->where('is_active', true)
            ->first();

        if (!$meetingType) {
            return response()->json(['message' => 'Meeting type not found'], 404);
        }

        // Parse month and get date range
        $monthStart = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // Don't show dates before today
        $startDate = $monthStart->lt(now()) ? now()->startOfDay() : $monthStart;

        // Don't show dates beyond max advance
        $maxDate = now()->addDays($meetingType->max_days_advance);
        $endDate = $monthEnd->gt($maxDate) ? $maxDate : $monthEnd;

        if ($startDate->gt($endDate)) {
            return response()->json([
                'available_dates' => [],
                'month' => $request->month,
            ]);
        }

        $availableDates = $this->availabilityService->getAvailableDates(
            $meetingType,
            $startDate,
            $endDate,
            $request->timezone
        );

        return response()->json([
            'available_dates' => $availableDates,
            'month' => $request->month,
        ]);
    }

    /**
     * Get available time slots for a specific date.
     */
    public function getAvailableSlots(Request $request, string $pageSlug, string $typeSlug): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'timezone' => 'required|timezone',
        ]);

        $page = SchedulingPage::where('slug', $pageSlug)
            ->where('is_active', true)
            ->first();

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $meetingType = MeetingType::where('scheduling_page_id', $page->id)
            ->where('slug', $typeSlug)
            ->where('is_active', true)
            ->first();

        if (!$meetingType) {
            return response()->json(['message' => 'Meeting type not found'], 404);
        }

        $date = Carbon::parse($request->date);

        $slots = $this->availabilityService->getAvailableSlots(
            $meetingType,
            $date,
            $request->timezone
        );

        return response()->json([
            'slots' => $slots,
            'date' => $request->date,
            'timezone' => $request->timezone,
        ]);
    }

    /**
     * Book a meeting.
     */
    public function book(Request $request, string $pageSlug, string $typeSlug): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'start_time' => 'required|date|after:now',
            'timezone' => 'required|timezone',
            'notes' => 'nullable|string|max:1000',
            'answers' => 'nullable|array',
        ]);

        $page = SchedulingPage::where('slug', $pageSlug)
            ->where('is_active', true)
            ->first();

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $meetingType = MeetingType::where('scheduling_page_id', $page->id)
            ->where('slug', $typeSlug)
            ->where('is_active', true)
            ->first();

        if (!$meetingType) {
            return response()->json(['message' => 'Meeting type not found'], 404);
        }

        try {
            $dto = CreateMeetingDTO::fromArray([
                'meeting_type_id' => $meetingType->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'start_time' => $validated['start_time'],
                'timezone' => $validated['timezone'],
                'notes' => $validated['notes'] ?? null,
                'answers' => $validated['answers'] ?? null,
            ]);

            $meetingResponse = $this->schedulingService->bookMeeting($dto);

            return response()->json([
                'message' => 'Meeting booked successfully',
                'meeting' => [
                    'id' => $meetingResponse->id,
                    'start_time' => $meetingResponse->startTime->format('c'),
                    'end_time' => $meetingResponse->endTime->format('c'),
                    'timezone' => $meetingResponse->timezone,
                    'location' => $meetingResponse->location,
                    'manage_url' => $meetingResponse->manageUrl,
                    'cancel_url' => $meetingResponse->cancelUrl,
                    'host' => [
                        'name' => $page->user->name,
                    ],
                    'meeting_type' => [
                        'name' => $meetingType->name,
                        'duration_minutes' => $meetingType->duration_minutes,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get meeting details by manage token.
     */
    public function getMeetingByToken(string $token): JsonResponse
    {
        $meetingResponse = $this->schedulingService->getMeetingByToken($token);

        if (!$meetingResponse) {
            return response()->json(['message' => 'Meeting not found'], 404);
        }

        // Load the Eloquent model to get related data
        $meeting = ScheduledMeeting::with(['host', 'meetingType.schedulingPage'])
            ->find($meetingResponse->id);

        return response()->json([
            'meeting' => [
                'id' => $meetingResponse->id,
                'attendee_name' => $meetingResponse->attendeeName,
                'attendee_email' => $meetingResponse->attendeeEmail,
                'start_time' => $meetingResponse->startTime->format('c'),
                'end_time' => $meetingResponse->endTime->format('c'),
                'timezone' => $meetingResponse->timezone,
                'location' => $meetingResponse->location,
                'status' => $meetingResponse->status,
                'can_cancel' => $meeting->can_cancel ?? true,
                'can_reschedule' => $meeting->can_reschedule ?? true,
                'host' => [
                    'name' => $meeting->host->name ?? 'Unknown',
                ],
                'meeting_type' => [
                    'name' => $meeting->meetingType->name ?? 'Unknown',
                    'slug' => $meeting->meetingType->slug ?? '',
                    'duration_minutes' => $meeting->meetingType->duration_minutes ?? 0,
                ],
                'page' => [
                    'slug' => $meeting->meetingType->schedulingPage->slug ?? '',
                ],
            ],
        ]);
    }

    /**
     * Cancel a meeting by token.
     */
    public function cancelByToken(Request $request, string $token): JsonResponse
    {
        $meetingResponse = $this->schedulingService->getMeetingByToken($token);

        if (!$meetingResponse) {
            return response()->json(['message' => 'Meeting not found'], 404);
        }

        // Check if can cancel via Eloquent model
        $meeting = ScheduledMeeting::find($meetingResponse->id);
        if ($meeting && !$meeting->can_cancel) {
            return response()->json(['message' => 'This meeting cannot be cancelled'], 422);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->schedulingService->cancelMeeting(
            $meetingResponse->id,
            $request->reason
        );

        return response()->json([
            'message' => 'Meeting cancelled successfully',
        ]);
    }

    /**
     * Reschedule a meeting by token.
     */
    public function rescheduleByToken(Request $request, string $token): JsonResponse
    {
        $meetingResponse = $this->schedulingService->getMeetingByToken($token);

        if (!$meetingResponse) {
            return response()->json(['message' => 'Meeting not found'], 404);
        }

        // Check if can reschedule via Eloquent model
        $meeting = ScheduledMeeting::find($meetingResponse->id);
        if ($meeting && !$meeting->can_reschedule) {
            return response()->json(['message' => 'This meeting cannot be rescheduled'], 422);
        }

        $validated = $request->validate([
            'start_time' => 'required|date|after:now',
            'timezone' => 'required|timezone',
        ]);

        try {
            $startTime = new DateTimeImmutable($validated['start_time']);

            // Calculate end time based on meeting type duration
            $meetingType = MeetingType::find($meetingResponse->meetingTypeId);
            $endTime = $startTime->modify("+{$meetingType->duration_minutes} minutes");

            $rescheduledMeeting = $this->schedulingService->rescheduleMeeting(
                $meetingResponse->id,
                $startTime,
                $endTime,
                $validated['timezone']
            );

            return response()->json([
                'message' => 'Meeting rescheduled successfully',
                'meeting' => [
                    'start_time' => $rescheduledMeeting->startTime->format('c'),
                    'end_time' => $rescheduledMeeting->endTime->format('c'),
                    'timezone' => $rescheduledMeeting->timezone,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
