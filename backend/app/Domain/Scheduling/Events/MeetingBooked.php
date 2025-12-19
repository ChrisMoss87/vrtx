<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Event raised when a meeting is booked.
 */
final class MeetingBooked extends DomainEvent
{
    public function __construct(
        private readonly int $meetingId,
        private readonly int $meetingTypeId,
        private readonly int $hostUserId,
        private readonly string $attendeeName,
        private readonly string $attendeeEmail,
        private readonly DateTimeImmutable $startTime,
        private readonly DateTimeImmutable $endTime,
        private readonly string $timezone,
        private readonly ?int $contactId,
    ) {
        parent::__construct();
    }

    public function aggregateId(): int
    {
        return $this->meetingId;
    }

    public function aggregateType(): string
    {
        return 'ScheduledMeeting';
    }

    public function meetingId(): int
    {
        return $this->meetingId;
    }

    public function meetingTypeId(): int
    {
        return $this->meetingTypeId;
    }

    public function hostUserId(): int
    {
        return $this->hostUserId;
    }

    public function attendeeName(): string
    {
        return $this->attendeeName;
    }

    public function attendeeEmail(): string
    {
        return $this->attendeeEmail;
    }

    public function startTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    public function endTime(): DateTimeImmutable
    {
        return $this->endTime;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function contactId(): ?int
    {
        return $this->contactId;
    }

    public function toPayload(): array
    {
        return [
            'meeting_id' => $this->meetingId,
            'meeting_type_id' => $this->meetingTypeId,
            'host_user_id' => $this->hostUserId,
            'attendee_name' => $this->attendeeName,
            'attendee_email' => $this->attendeeEmail,
            'start_time' => $this->startTime->format('c'),
            'end_time' => $this->endTime->format('c'),
            'timezone' => $this->timezone,
            'contact_id' => $this->contactId,
        ];
    }
}
