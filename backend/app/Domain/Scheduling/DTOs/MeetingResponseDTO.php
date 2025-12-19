<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\DTOs;

use App\Domain\Scheduling\Entities\ScheduledMeeting;
use JsonSerializable;

/**
 * Data Transfer Object for scheduled meeting responses.
 */
final readonly class MeetingResponseDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $meetingTypeId,
        public int $hostUserId,
        public ?int $contactId,
        public string $attendeeName,
        public string $attendeeEmail,
        public ?string $attendeePhone,
        public string $startTime,
        public string $endTime,
        public string $timezone,
        public string $location,
        public ?string $notes,
        public ?array $answers,
        public string $status,
        public ?string $calendarEventId,
        public string $manageToken,
        public bool $reminderSent,
        public ?string $cancelledAt,
        public ?string $cancellationReason,
        public bool $isUpcoming,
        public int $durationMinutes,
        public string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromEntity(ScheduledMeeting $meeting): self
    {
        return new self(
            id: $meeting->getId(),
            meetingTypeId: $meeting->meetingTypeId(),
            hostUserId: $meeting->hostUserId()->value(),
            contactId: $meeting->contactId(),
            attendeeName: $meeting->attendeeName(),
            attendeeEmail: $meeting->attendeeEmail(),
            attendeePhone: $meeting->attendeePhone(),
            startTime: $meeting->startTime()->format('c'),
            endTime: $meeting->endTime()->format('c'),
            timezone: $meeting->timezone(),
            location: $meeting->location(),
            notes: $meeting->notes(),
            answers: $meeting->answers(),
            status: $meeting->status()->value,
            calendarEventId: $meeting->calendarEventId(),
            manageToken: $meeting->manageToken(),
            reminderSent: $meeting->reminderSent(),
            cancelledAt: $meeting->cancelledAt()?->format('c'),
            cancellationReason: $meeting->cancellationReason(),
            isUpcoming: $meeting->isUpcoming(),
            durationMinutes: $meeting->durationMinutes(),
            createdAt: $meeting->createdAt()?->toDateTimeString() ?? '',
            updatedAt: $meeting->updatedAt()?->toDateTimeString(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'meeting_type_id' => $this->meetingTypeId,
            'host_user_id' => $this->hostUserId,
            'contact_id' => $this->contactId,
            'attendee_name' => $this->attendeeName,
            'attendee_email' => $this->attendeeEmail,
            'attendee_phone' => $this->attendeePhone,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'timezone' => $this->timezone,
            'location' => $this->location,
            'notes' => $this->notes,
            'answers' => $this->answers,
            'status' => $this->status,
            'calendar_event_id' => $this->calendarEventId,
            'manage_token' => $this->manageToken,
            'reminder_sent' => $this->reminderSent,
            'cancelled_at' => $this->cancelledAt,
            'cancellation_reason' => $this->cancellationReason,
            'is_upcoming' => $this->isUpcoming,
            'duration_minutes' => $this->durationMinutes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
