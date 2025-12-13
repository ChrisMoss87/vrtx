<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\MeetingType;
use App\Models\ScheduledMeeting;
use App\Models\SchedulingPage;
use App\Services\Scheduling\AvailabilityService;
use App\Services\Scheduling\MeetingBookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected MeetingBookingService $bookingService
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
            $meeting = $this->bookingService->bookMeeting($meetingType, $validated);

            return response()->json([
                'message' => 'Meeting booked successfully',
                'meeting' => [
                    'id' => $meeting->id,
                    'start_time' => $meeting->start_time->toIso8601String(),
                    'end_time' => $meeting->end_time->toIso8601String(),
                    'timezone' => $meeting->timezone,
                    'location' => $meeting->location,
                    'manage_url' => $meeting->manage_url,
                    'cancel_url' => $meeting->cancel_url,
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
        $meeting = $this->bookingService->getMeetingByToken($token);

        if (!$meeting) {
            return response()->json(['message' => 'Meeting not found'], 404);
        }

        return response()->json([
            'meeting' => [
                'id' => $meeting->id,
                'attendee_name' => $meeting->attendee_name,
                'attendee_email' => $meeting->attendee_email,
                'start_time' => $meeting->start_time->toIso8601String(),
                'end_time' => $meeting->end_time->toIso8601String(),
                'timezone' => $meeting->timezone,
                'location' => $meeting->location,
                'status' => $meeting->status,
                'can_cancel' => $meeting->can_cancel,
                'can_reschedule' => $meeting->can_reschedule,
                'host' => [
                    'name' => $meeting->host->name,
                ],
                'meeting_type' => [
                    'name' => $meeting->meetingType->name,
                    'slug' => $meeting->meetingType->slug,
                    'duration_minutes' => $meeting->meetingType->duration_minutes,
                ],
                'page' => [
                    'slug' => $meeting->meetingType->schedulingPage->slug,
                ],
            ],
        ]);
    }

    /**
     * Cancel a meeting by token.
     */
    public function cancelByToken(Request $request, string $token): JsonResponse
    {
        $meeting = $this->bookingService->getMeetingByToken($token);

        if (!$meeting) {
            return response()->json(['message' => 'Meeting not found'], 404);
        }

        if (!$meeting->can_cancel) {
            return response()->json(['message' => 'This meeting cannot be cancelled'], 422);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->bookingService->cancelMeeting($meeting, $request->reason);

        return response()->json([
            'message' => 'Meeting cancelled successfully',
        ]);
    }

    /**
     * Reschedule a meeting by token.
     */
    public function rescheduleByToken(Request $request, string $token): JsonResponse
    {
        $meeting = $this->bookingService->getMeetingByToken($token);

        if (!$meeting) {
            return response()->json(['message' => 'Meeting not found'], 404);
        }

        if (!$meeting->can_reschedule) {
            return response()->json(['message' => 'This meeting cannot be rescheduled'], 422);
        }

        $validated = $request->validate([
            'start_time' => 'required|date|after:now',
            'timezone' => 'required|timezone',
        ]);

        try {
            $meeting = $this->bookingService->rescheduleMeeting(
                $meeting,
                Carbon::parse($validated['start_time']),
                $validated['timezone']
            );

            return response()->json([
                'message' => 'Meeting rescheduled successfully',
                'meeting' => [
                    'start_time' => $meeting->start_time->toIso8601String(),
                    'end_time' => $meeting->end_time->toIso8601String(),
                    'timezone' => $meeting->timezone,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
