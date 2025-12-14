<?php

declare(strict_types=1);

namespace App\Application\Services\Scheduling;

use App\Domain\Scheduling\DTOs\CreateMeetingDTO;
use App\Domain\Scheduling\DTOs\MeetingResponseDTO;
use App\Domain\Scheduling\Entities\ScheduledMeeting;
use App\Domain\Scheduling\Events\MeetingBooked;
use App\Domain\Scheduling\Events\MeetingCancelled;
use App\Domain\Scheduling\Events\MeetingRescheduled;
use App\Domain\Scheduling\Repositories\ScheduledMeetingRepositoryInterface;
use App\Domain\Scheduling\Repositories\SchedulingPageRepositoryInterface;
use App\Domain\Scheduling\Services\AvailabilityCalculatorService;
use App\Domain\Scheduling\Services\BookingValidationService;
use App\Domain\Scheduling\Services\ConflictDetectionService;
use App\Domain\Scheduling\ValueObjects\MeetingDuration;
use App\Domain\Scheduling\ValueObjects\TimeSlot;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Application service for scheduling operations.
 *
 * Orchestrates domain services and repositories to fulfill use cases.
 */
final class SchedulingApplicationService
{
    public function __construct(
        private readonly SchedulingPageRepositoryInterface $pageRepository,
        private readonly ScheduledMeetingRepositoryInterface $meetingRepository,
        private readonly AvailabilityCalculatorService $availabilityCalculator,
        private readonly ConflictDetectionService $conflictDetector,
        private readonly BookingValidationService $bookingValidator,
    ) {}

    /**
     * Book a new meeting.
     */
    public function bookMeeting(CreateMeetingDTO $dto): MeetingResponseDTO
    {
        // Get the meeting type (this would need a repository method or service)
        // For now we'll assume we have the meeting type entity
        // $meetingType = $this->getMeetingType($dto->meetingTypeId);

        // Calculate end time based on duration
        // $endTime = $dto->startTime->modify("+{$meetingType->duration()->minutes()} minutes");

        // For now, let's assume a 30-minute duration
        $duration = new MeetingDuration(30);
        $endTime = $dto->startTime->modify("+{$duration->minutes()} minutes");

        // Create the time slot
        $proposedSlot = new TimeSlot($dto->startTime, $endTime);

        // Get available slots for validation
        // In a real implementation, you would:
        // 1. Get the meeting type
        // 2. Calculate available slots
        // 3. Validate the booking
        $availableSlots = []; // TODO: Get from availability calculator

        // Validate the booking
        // $this->bookingValidator->validateBooking(
        //     $meetingType,
        //     $dto->startTime,
        //     $endTime,
        //     $proposedSlot,
        //     $availableSlots
        // );

        // Create the meeting entity
        $meeting = ScheduledMeeting::book(
            meetingTypeId: $dto->meetingTypeId,
            hostUserId: UserId::fromInt(1), // TODO: Get from meeting type
            attendeeName: $dto->attendeeName,
            attendeeEmail: $dto->attendeeEmail,
            startTime: $dto->startTime,
            endTime: $endTime,
            timezone: $dto->timezone,
            location: 'Virtual', // TODO: Get from meeting type
            contactId: null,
            attendeePhone: $dto->attendeePhone,
            notes: $dto->notes,
            answers: $dto->answers,
        );

        // Persist the meeting
        $savedMeeting = $this->meetingRepository->save($meeting);

        // Raise domain event
        $savedMeeting->recordEvent(new MeetingBooked(
            meetingId: $savedMeeting->getId(),
            meetingTypeId: $savedMeeting->meetingTypeId(),
            hostUserId: $savedMeeting->hostUserId()->value(),
            attendeeName: $savedMeeting->attendeeName(),
            attendeeEmail: $savedMeeting->attendeeEmail(),
            startTime: $savedMeeting->startTime(),
            endTime: $savedMeeting->endTime(),
            timezone: $savedMeeting->timezone(),
            contactId: $savedMeeting->contactId(),
        ));

        // Dispatch events
        $this->dispatchDomainEvents($savedMeeting);

        return MeetingResponseDTO::fromEntity($savedMeeting);
    }

    /**
     * Reschedule a meeting.
     */
    public function rescheduleMeeting(
        int $meetingId,
        DateTimeImmutable $newStartTime,
        DateTimeImmutable $newEndTime,
        string $timezone,
        ?int $rescheduledByUserId = null
    ): MeetingResponseDTO {
        $meeting = $this->meetingRepository->findById($meetingId);

        if (!$meeting) {
            throw new InvalidArgumentException('Meeting not found');
        }

        // Store old times for the event
        $oldStartTime = $meeting->startTime();
        $oldEndTime = $meeting->endTime();

        // Reschedule the meeting
        $meeting->reschedule($newStartTime, $newEndTime, $timezone);

        // Persist changes
        $savedMeeting = $this->meetingRepository->save($meeting);

        // Raise domain event
        $savedMeeting->recordEvent(new MeetingRescheduled(
            meetingId: $savedMeeting->getId(),
            hostUserId: $savedMeeting->hostUserId()->value(),
            attendeeEmail: $savedMeeting->attendeeEmail(),
            oldStartTime: $oldStartTime,
            oldEndTime: $oldEndTime,
            newStartTime: $savedMeeting->startTime(),
            newEndTime: $savedMeeting->endTime(),
            timezone: $savedMeeting->timezone(),
            rescheduledByUserId: $rescheduledByUserId,
        ));

        // Dispatch events
        $this->dispatchDomainEvents($savedMeeting);

        return MeetingResponseDTO::fromEntity($savedMeeting);
    }

    /**
     * Cancel a meeting.
     */
    public function cancelMeeting(
        int $meetingId,
        ?string $reason = null,
        ?int $cancelledByUserId = null
    ): MeetingResponseDTO {
        $meeting = $this->meetingRepository->findById($meetingId);

        if (!$meeting) {
            throw new InvalidArgumentException('Meeting not found');
        }

        // Cancel the meeting
        $meeting->cancel($reason);

        // Persist changes
        $savedMeeting = $this->meetingRepository->save($meeting);

        // Raise domain event
        $savedMeeting->recordEvent(new MeetingCancelled(
            meetingId: $savedMeeting->getId(),
            hostUserId: $savedMeeting->hostUserId()->value(),
            attendeeEmail: $savedMeeting->attendeeEmail(),
            reason: $reason,
            cancelledByUserId: $cancelledByUserId,
        ));

        // Dispatch events
        $this->dispatchDomainEvents($savedMeeting);

        return MeetingResponseDTO::fromEntity($savedMeeting);
    }

    /**
     * Get a meeting by its manage token.
     */
    public function getMeetingByToken(string $token): ?MeetingResponseDTO
    {
        $meeting = $this->meetingRepository->findByManageToken($token);

        if (!$meeting) {
            return null;
        }

        return MeetingResponseDTO::fromEntity($meeting);
    }

    /**
     * Get upcoming meetings for a host user.
     *
     * @return array<MeetingResponseDTO>
     */
    public function getUpcomingMeetings(int $userId): array
    {
        $meetings = $this->meetingRepository->findUpcomingByHostUserId(UserId::fromInt($userId));

        return array_map(
            fn(ScheduledMeeting $m) => MeetingResponseDTO::fromEntity($m),
            $meetings
        );
    }

    /**
     * Dispatch domain events from an aggregate root.
     */
    private function dispatchDomainEvents(ScheduledMeeting $meeting): void
    {
        $events = $meeting->pullDomainEvents();

        foreach ($events as $event) {
            // In a real implementation, dispatch to an event bus
            // event($event);
        }
    }
}
