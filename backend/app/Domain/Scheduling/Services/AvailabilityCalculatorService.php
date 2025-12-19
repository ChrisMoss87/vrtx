<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\Entities\AvailabilityRule;
use App\Domain\Scheduling\Entities\MeetingType;
use App\Domain\Scheduling\Entities\SchedulingOverride;
use App\Domain\Scheduling\ValueObjects\DayOfWeek;
use App\Domain\Scheduling\ValueObjects\TimeSlot;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;

/**
 * Domain service for calculating available time slots.
 */
final class AvailabilityCalculatorService
{
    /**
     * Get available time slots for a meeting type on a specific date.
     *
     * @return array<TimeSlot>
     */
    public function calculateAvailableSlots(
        MeetingType $meetingType,
        DateTimeImmutable $date,
        array $availabilityRules,
        ?SchedulingOverride $override,
        array $busyPeriods,
        string $userTimezone
    ): array {
        // Check minimum notice
        $minNoticeTime = (new DateTimeImmutable())->modify("+{$meetingType->minNoticeHours()} hours");
        if ($date->setTime(23, 59, 59) < $minNoticeTime) {
            return [];
        }

        // Check maximum days in advance
        $maxDate = (new DateTimeImmutable())->modify("+{$meetingType->maxDaysAdvance()} days");
        if ($date > $maxDate) {
            return [];
        }

        // Get availability windows for this day
        $dayOfWeek = DayOfWeek::from((int) $date->format('w'));
        $windows = $this->getAvailabilityWindows($dayOfWeek, $availabilityRules, $override);

        if (empty($windows)) {
            return [];
        }

        // Generate all possible slots
        $slots = $this->generateSlots($windows, $meetingType, $date, $userTimezone);

        // Filter out busy slots
        return $this->filterBusySlots($slots, $busyPeriods, $minNoticeTime);
    }

    /**
     * Get availability windows for a specific day.
     *
     * @param array<AvailabilityRule> $rules
     * @return array<array{start: string, end: string}>
     */
    private function getAvailabilityWindows(
        DayOfWeek $dayOfWeek,
        array $rules,
        ?SchedulingOverride $override
    ): array {
        // Check for date-specific override first
        if ($override !== null) {
            if (!$override->isAvailable()) {
                return [];
            }

            if ($override->isCustomHours()) {
                return [[
                    'start' => $override->startTime(),
                    'end' => $override->endTime(),
                ]];
            }
        }

        // Get regular availability rules for this day
        $windows = [];
        foreach ($rules as $rule) {
            if ($rule->dayOfWeek()->value === $dayOfWeek->value && $rule->isAvailable()) {
                $windows[] = [
                    'start' => $rule->startTime(),
                    'end' => $rule->endTime(),
                ];
            }
        }

        return $windows;
    }

    /**
     * Generate time slots from availability windows.
     *
     * @return array<TimeSlot>
     */
    private function generateSlots(
        array $windows,
        MeetingType $meetingType,
        DateTimeImmutable $date,
        string $timezone
    ): array {
        $slots = [];
        $slotInterval = $meetingType->slotInterval();
        $duration = $meetingType->duration()->minutes();
        $bufferBefore = $meetingType->bufferBefore();
        $bufferAfter = $meetingType->bufferAfter();

        foreach ($windows as $window) {
            $windowStart = new DateTimeImmutable(
                $date->format('Y-m-d') . ' ' . $window['start'],
                new \DateTimeZone($timezone)
            );
            $windowEnd = new DateTimeImmutable(
                $date->format('Y-m-d') . ' ' . $window['end'],
                new \DateTimeZone($timezone)
            );

            // Adjust for buffer time
            $effectiveEnd = $windowEnd->modify("-{$duration} minutes")->modify("-{$bufferAfter} minutes");
            $currentSlot = $windowStart->modify("+{$bufferBefore} minutes");

            while ($currentSlot <= $effectiveEnd) {
                $slotEnd = $currentSlot->modify("+{$duration} minutes");

                $slots[] = TimeSlot::withBuffer(
                    start: $currentSlot,
                    end: $slotEnd,
                    bufferBeforeMinutes: $bufferBefore,
                    bufferAfterMinutes: $bufferAfter
                );

                $currentSlot = $currentSlot->modify("+{$slotInterval} minutes");
            }
        }

        return $slots;
    }

    /**
     * Filter out slots that conflict with busy periods.
     *
     * @param array<TimeSlot> $slots
     * @param array<array{start: DateTimeImmutable, end: DateTimeImmutable}> $busyPeriods
     * @return array<TimeSlot>
     */
    private function filterBusySlots(
        array $slots,
        array $busyPeriods,
        DateTimeImmutable $minNoticeTime
    ): array {
        return array_values(array_filter($slots, function (TimeSlot $slot) use ($busyPeriods, $minNoticeTime) {
            // Check minimum notice
            if ($slot->start() < $minNoticeTime) {
                return false;
            }

            // Check for conflicts with busy periods
            foreach ($busyPeriods as $busy) {
                $busySlot = new TimeSlot($busy['start'], $busy['end']);
                if ($slot->overlaps($busySlot, true)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
