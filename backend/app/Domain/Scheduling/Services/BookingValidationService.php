<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\Entities\MeetingType;
use App\Domain\Scheduling\ValueObjects\TimeSlot;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Domain service for validating meeting bookings.
 */
final class BookingValidationService
{
    /**
     * Validate that a booking request meets all requirements.
     *
     * @throws InvalidArgumentException
     */
    public function validateBooking(
        MeetingType $meetingType,
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime,
        TimeSlot $proposedSlot,
        array $availableSlots
    ): void {
        // Check if meeting type is active
        if (!$meetingType->isActive()) {
            throw new InvalidArgumentException('This meeting type is not currently available for booking');
        }

        // Check if start time is in the past
        if ($startTime < new DateTimeImmutable()) {
            throw new InvalidArgumentException('Cannot book a meeting in the past');
        }

        // Check minimum notice period
        $minNoticeTime = (new DateTimeImmutable())->modify("+{$meetingType->minNoticeHours()} hours");
        if ($startTime < $minNoticeTime) {
            throw new InvalidArgumentException(
                sprintf('This meeting requires at least %d hours notice', $meetingType->minNoticeHours())
            );
        }

        // Check maximum days in advance
        $maxDate = (new DateTimeImmutable())->modify("+{$meetingType->maxDaysAdvance()} days");
        if ($startTime > $maxDate) {
            throw new InvalidArgumentException(
                sprintf('Cannot book more than %d days in advance', $meetingType->maxDaysAdvance())
            );
        }

        // Validate duration matches meeting type
        $expectedDuration = $meetingType->duration()->minutes();
        $actualDuration = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60;
        if ($actualDuration !== $expectedDuration) {
            throw new InvalidArgumentException(
                sprintf('Meeting duration must be exactly %d minutes', $expectedDuration)
            );
        }

        // Check if the requested slot is available
        if (!$this->isSlotAvailable($proposedSlot, $availableSlots)) {
            throw new InvalidArgumentException('This time slot is no longer available. Please select another time.');
        }
    }

    /**
     * Check if a proposed slot is in the list of available slots.
     *
     * @param array<TimeSlot> $availableSlots
     */
    private function isSlotAvailable(TimeSlot $proposedSlot, array $availableSlots): bool
    {
        foreach ($availableSlots as $availableSlot) {
            if ($proposedSlot->equals($availableSlot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate rescheduling request.
     *
     * @throws InvalidArgumentException
     */
    public function validateReschedule(
        DateTimeImmutable $newStartTime,
        DateTimeImmutable $newEndTime,
        array $availableSlots
    ): void {
        if ($newStartTime < new DateTimeImmutable()) {
            throw new InvalidArgumentException('Cannot reschedule to a past time');
        }

        $proposedSlot = new TimeSlot($newStartTime, $newEndTime);

        if (!$this->isSlotAvailable($proposedSlot, $availableSlots)) {
            throw new InvalidArgumentException('This time slot is not available. Please select another time.');
        }
    }
}
