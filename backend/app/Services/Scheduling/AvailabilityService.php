<?php

namespace App\Services\Scheduling;

use App\Models\AvailabilityRule;
use App\Models\CalendarConnection;
use App\Models\CalendarEventCache;
use App\Models\MeetingType;
use App\Models\ScheduledMeeting;
use App\Models\SchedulingOverride;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * Get available time slots for a meeting type on a specific date.
     */
    public function getAvailableSlots(
        MeetingType $meetingType,
        Carbon $date,
        string $timezone = 'UTC'
    ): array {
        $user = $meetingType->schedulingPage->user;
        $userTimezone = $meetingType->schedulingPage->timezone;

        // Convert date to user's timezone
        $dateInUserTz = $date->copy()->setTimezone($userTimezone)->startOfDay();

        // Check minimum notice
        $minNoticeTime = now()->addHours($meetingType->min_notice_hours);
        if ($dateInUserTz->endOfDay()->lt($minNoticeTime)) {
            return []; // Date is within minimum notice period
        }

        // Check maximum days in advance
        $maxDate = now()->addDays($meetingType->max_days_advance);
        if ($dateInUserTz->startOfDay()->gt($maxDate)) {
            return []; // Date is too far in the future
        }

        // Get base availability for this day
        $dayOfWeek = $dateInUserTz->dayOfWeek;
        $availabilityWindows = $this->getAvailabilityWindows($user, $dayOfWeek, $dateInUserTz);

        if (empty($availabilityWindows)) {
            return []; // Not available on this day
        }

        // Generate all possible slots
        $slots = $this->generateSlots(
            $availabilityWindows,
            $meetingType,
            $dateInUserTz
        );

        // Filter out busy times
        $slots = $this->filterBusySlots($slots, $user, $meetingType, $dateInUserTz);

        // Convert to requested timezone
        return $this->formatSlotsForTimezone($slots, $userTimezone, $timezone);
    }

    /**
     * Get availability windows for a user on a specific day.
     */
    public function getAvailabilityWindows(User $user, int $dayOfWeek, Carbon $date): array
    {
        // Check for date-specific override first
        $override = SchedulingOverride::where('user_id', $user->id)
            ->where('date', $date->toDateString())
            ->first();

        if ($override) {
            if (!$override->is_available) {
                return []; // Day is blocked
            }

            if ($override->start_time && $override->end_time) {
                return [[
                    'start' => $override->start_time,
                    'end' => $override->end_time,
                ]];
            }
        }

        // Get regular availability rules
        $rules = AvailabilityRule::where('user_id', $user->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->orderBy('start_time')
            ->get();

        if ($rules->isEmpty()) {
            return [];
        }

        return $rules->map(fn($rule) => [
            'start' => $rule->start_time,
            'end' => $rule->end_time,
        ])->toArray();
    }

    /**
     * Generate time slots from availability windows.
     */
    protected function generateSlots(
        array $availabilityWindows,
        MeetingType $meetingType,
        Carbon $date
    ): array {
        $slots = [];
        $slotInterval = $meetingType->slot_interval;
        $duration = $meetingType->duration_minutes;
        $bufferBefore = $meetingType->buffer_before;
        $bufferAfter = $meetingType->buffer_after;

        foreach ($availabilityWindows as $window) {
            $windowStart = Carbon::parse($date->toDateString() . ' ' . $window['start']);
            $windowEnd = Carbon::parse($date->toDateString() . ' ' . $window['end']);

            // Adjust for buffer time
            $effectiveEnd = $windowEnd->copy()->subMinutes($duration + $bufferAfter);

            $currentSlot = $windowStart->copy()->addMinutes($bufferBefore);

            while ($currentSlot->lte($effectiveEnd)) {
                $slotEnd = $currentSlot->copy()->addMinutes($duration);

                $slots[] = [
                    'start' => $currentSlot->copy(),
                    'end' => $slotEnd->copy(),
                    'buffer_start' => $currentSlot->copy()->subMinutes($bufferBefore),
                    'buffer_end' => $slotEnd->copy()->addMinutes($bufferAfter),
                ];

                $currentSlot->addMinutes($slotInterval);
            }
        }

        return $slots;
    }

    /**
     * Filter out slots that conflict with existing events.
     */
    protected function filterBusySlots(
        array $slots,
        User $user,
        MeetingType $meetingType,
        Carbon $date
    ): array {
        $busyPeriods = $this->getBusyPeriods($user, $date);

        // Check minimum notice
        $minNoticeTime = now()->addHours($meetingType->min_notice_hours);

        return array_values(array_filter($slots, function ($slot) use ($busyPeriods, $minNoticeTime) {
            // Check minimum notice
            if ($slot['start']->lt($minNoticeTime)) {
                return false;
            }

            // Check for conflicts with busy periods
            foreach ($busyPeriods as $busy) {
                if ($this->periodsOverlap(
                    $slot['buffer_start'],
                    $slot['buffer_end'],
                    $busy['start'],
                    $busy['end']
                )) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Get all busy periods for a user on a date.
     */
    public function getBusyPeriods(User $user, Carbon $date): array
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $busyPeriods = [];

        // Get scheduled meetings
        $meetings = ScheduledMeeting::where('host_user_id', $user->id)
            ->where('status', ScheduledMeeting::STATUS_SCHEDULED)
            ->where('start_time', '>=', $dayStart)
            ->where('start_time', '<=', $dayEnd)
            ->get();

        foreach ($meetings as $meeting) {
            // Include buffer times from the meeting type
            $meetingType = $meeting->meetingType;
            $busyPeriods[] = [
                'start' => $meeting->start_time->copy()->subMinutes($meetingType->buffer_before ?? 0),
                'end' => $meeting->end_time->copy()->addMinutes($meetingType->buffer_after ?? 0),
                'type' => 'meeting',
            ];
        }

        // Get calendar events from connected calendars
        $calendarConnections = CalendarConnection::where('user_id', $user->id)
            ->where('sync_enabled', true)
            ->get();

        foreach ($calendarConnections as $connection) {
            $events = CalendarEventCache::where('calendar_connection_id', $connection->id)
                ->active()
                ->inRange($dayStart, $dayEnd)
                ->get();

            foreach ($events as $event) {
                if ($event->is_all_day) {
                    $busyPeriods[] = [
                        'start' => $dayStart->copy(),
                        'end' => $dayEnd->copy(),
                        'type' => 'calendar_all_day',
                    ];
                } else {
                    $busyPeriods[] = [
                        'start' => $event->start_time,
                        'end' => $event->end_time,
                        'type' => 'calendar',
                    ];
                }
            }
        }

        return $busyPeriods;
    }

    /**
     * Check if two time periods overlap.
     */
    protected function periodsOverlap(Carbon $start1, Carbon $end1, Carbon $start2, Carbon $end2): bool
    {
        return $start1->lt($end2) && $end1->gt($start2);
    }

    /**
     * Format slots for a specific timezone.
     */
    protected function formatSlotsForTimezone(array $slots, string $fromTz, string $toTz): array
    {
        return array_map(function ($slot) use ($fromTz, $toTz) {
            $start = $slot['start']->copy()->setTimezone($fromTz)->setTimezone($toTz);
            $end = $slot['end']->copy()->setTimezone($fromTz)->setTimezone($toTz);

            return [
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'start_formatted' => $start->format('g:i A'),
                'end_formatted' => $end->format('g:i A'),
            ];
        }, $slots);
    }

    /**
     * Get available dates for a meeting type.
     */
    public function getAvailableDates(
        MeetingType $meetingType,
        Carbon $startDate,
        Carbon $endDate,
        string $timezone = 'UTC'
    ): array {
        $availableDates = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $slots = $this->getAvailableSlots($meetingType, $date, $timezone);
            if (!empty($slots)) {
                $availableDates[] = $date->toDateString();
            }
        }

        return $availableDates;
    }

    /**
     * Check if a specific time slot is available.
     */
    public function isSlotAvailable(
        MeetingType $meetingType,
        Carbon $startTime,
        string $timezone = 'UTC'
    ): bool {
        $date = $startTime->copy()->setTimezone($meetingType->schedulingPage->timezone)->startOfDay();
        $slots = $this->getAvailableSlots($meetingType, $date, $timezone);

        $startIso = $startTime->copy()->setTimezone($timezone)->toIso8601String();

        foreach ($slots as $slot) {
            if ($slot['start'] === $startIso) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initialize default availability rules for a user.
     */
    public function initializeDefaultAvailability(User $user): void
    {
        $defaultRules = AvailabilityRule::getDefaultRules();

        foreach ($defaultRules as $rule) {
            AvailabilityRule::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'day_of_week' => $rule['day_of_week'],
                ],
                [
                    'start_time' => $rule['start_time'],
                    'end_time' => $rule['end_time'],
                    'is_available' => $rule['is_available'],
                ]
            );
        }
    }
}
