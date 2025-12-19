<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\Entities\ScheduledMeeting;
use App\Domain\Scheduling\ValueObjects\TimeSlot;
use DateTimeImmutable;

/**
 * Domain service for detecting scheduling conflicts.
 */
final class ConflictDetectionService
{
    /**
     * Check if a proposed time slot conflicts with existing meetings.
     *
     * @param array<ScheduledMeeting> $existingMeetings
     */
    public function hasConflict(
        TimeSlot $proposedSlot,
        array $existingMeetings,
        bool $includeBuffer = true
    ): bool {
        foreach ($existingMeetings as $meeting) {
            $meetingSlot = new TimeSlot(
                start: $meeting->startTime(),
                end: $meeting->endTime()
            );

            if ($proposedSlot->overlaps($meetingSlot, $includeBuffer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all conflicting meetings for a time slot.
     *
     * @param array<ScheduledMeeting> $existingMeetings
     * @return array<ScheduledMeeting>
     */
    public function getConflictingMeetings(
        TimeSlot $proposedSlot,
        array $existingMeetings,
        bool $includeBuffer = true
    ): array {
        $conflicts = [];

        foreach ($existingMeetings as $meeting) {
            $meetingSlot = new TimeSlot(
                start: $meeting->startTime(),
                end: $meeting->endTime()
            );

            if ($proposedSlot->overlaps($meetingSlot, $includeBuffer)) {
                $conflicts[] = $meeting;
            }
        }

        return $conflicts;
    }

    /**
     * Check if two time periods overlap.
     */
    public function periodsOverlap(
        DateTimeImmutable $start1,
        DateTimeImmutable $end1,
        DateTimeImmutable $start2,
        DateTimeImmutable $end2
    ): bool {
        return $start1 < $end2 && $end1 > $start2;
    }
}
