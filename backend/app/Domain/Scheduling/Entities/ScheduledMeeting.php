<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Entities;

use App\Domain\Scheduling\ValueObjects\MeetingStatus;
use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Events\DomainEvent;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * ScheduledMeeting aggregate root entity.
 *
 * Represents a scheduled meeting between a host and an attendee.
 */
final class ScheduledMeeting implements AggregateRoot
{
    use HasDomainEvents;

    private function __construct(
        private ?int $id,
        private int $meetingTypeId,
        private UserId $hostUserId,
        private ?int $contactId,
        private string $attendeeName,
        private string $attendeeEmail,
        private ?string $attendeePhone,
        private DateTimeImmutable $startTime,
        private DateTimeImmutable $endTime,
        private string $timezone,
        private string $location,
        private ?string $notes,
        private ?array $answers,
        private MeetingStatus $status,
        private ?string $calendarEventId,
        private string $manageToken,
        private bool $reminderSent,
        private ?DateTimeImmutable $cancelledAt,
        private ?string $cancellationReason,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Book a new meeting.
     */
    public static function book(
        int $meetingTypeId,
        UserId $hostUserId,
        string $attendeeName,
        string $attendeeEmail,
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime,
        string $timezone,
        string $location,
        ?int $contactId = null,
        ?string $attendeePhone = null,
        ?string $notes = null,
        ?array $answers = null,
    ): self {
        self::validateAttendeeInfo($attendeeName, $attendeeEmail);
        self::validateTimeRange($startTime, $endTime);
        self::validateTimezone($timezone);

        $manageToken = bin2hex(random_bytes(32));

        return new self(
            id: null,
            meetingTypeId: $meetingTypeId,
            hostUserId: $hostUserId,
            contactId: $contactId,
            attendeeName: $attendeeName,
            attendeeEmail: $attendeeEmail,
            attendeePhone: $attendeePhone,
            startTime: $startTime,
            endTime: $endTime,
            timezone: $timezone,
            location: $location,
            notes: $notes,
            answers: $answers,
            status: MeetingStatus::SCHEDULED,
            calendarEventId: null,
            manageToken: $manageToken,
            reminderSent: false,
            cancelledAt: null,
            cancellationReason: null,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        int $meetingTypeId,
        UserId $hostUserId,
        ?int $contactId,
        string $attendeeName,
        string $attendeeEmail,
        ?string $attendeePhone,
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime,
        string $timezone,
        string $location,
        ?string $notes,
        ?array $answers,
        MeetingStatus $status,
        ?string $calendarEventId,
        string $manageToken,
        bool $reminderSent,
        ?DateTimeImmutable $cancelledAt,
        ?string $cancellationReason,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            meetingTypeId: $meetingTypeId,
            hostUserId: $hostUserId,
            contactId: $contactId,
            attendeeName: $attendeeName,
            attendeeEmail: $attendeeEmail,
            attendeePhone: $attendeePhone,
            startTime: $startTime,
            endTime: $endTime,
            timezone: $timezone,
            location: $location,
            notes: $notes,
            answers: $answers,
            status: $status,
            calendarEventId: $calendarEventId,
            manageToken: $manageToken,
            reminderSent: $reminderSent,
            cancelledAt: $cancelledAt,
            cancellationReason: $cancellationReason,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Reschedule the meeting to a new time.
     */
    public function reschedule(
        DateTimeImmutable $newStartTime,
        DateTimeImmutable $newEndTime,
        string $timezone,
    ): void {
        if (!$this->status->canBeRescheduled()) {
            throw new InvalidArgumentException(
                'Meeting cannot be rescheduled in status: ' . $this->status->value
            );
        }

        if ($newStartTime < new DateTimeImmutable()) {
            throw new InvalidArgumentException('Cannot reschedule to a past time');
        }

        self::validateTimeRange($newStartTime, $newEndTime);
        self::validateTimezone($timezone);

        $this->startTime = $newStartTime;
        $this->endTime = $newEndTime;
        $this->timezone = $timezone;
        $this->status = MeetingStatus::SCHEDULED;
        $this->reminderSent = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Cancel the meeting.
     */
    public function cancel(?string $reason = null): void
    {
        if (!$this->status->canBeCancelled()) {
            throw new InvalidArgumentException(
                'Meeting cannot be cancelled in status: ' . $this->status->value
            );
        }

        $this->status = MeetingStatus::CANCELLED;
        $this->cancelledAt = new DateTimeImmutable();
        $this->cancellationReason = $reason;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Mark the meeting as completed.
     */
    public function markCompleted(): void
    {
        if (!$this->status->canTransitionTo(MeetingStatus::COMPLETED)) {
            throw new InvalidArgumentException(
                'Meeting cannot be marked as completed from status: ' . $this->status->value
            );
        }

        $this->status = MeetingStatus::COMPLETED;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Mark the meeting as no-show.
     */
    public function markNoShow(): void
    {
        if (!$this->status->canTransitionTo(MeetingStatus::NO_SHOW)) {
            throw new InvalidArgumentException(
                'Meeting cannot be marked as no-show from status: ' . $this->status->value
            );
        }

        $this->status = MeetingStatus::NO_SHOW;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Mark that a reminder was sent.
     */
    public function markReminderSent(): void
    {
        $this->reminderSent = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Link to a calendar event.
     */
    public function linkCalendarEvent(string $calendarEventId): void
    {
        $this->calendarEventId = $calendarEventId;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Link to a contact record.
     */
    public function linkContact(int $contactId): void
    {
        $this->contactId = $contactId;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Check if the meeting is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->status === MeetingStatus::SCHEDULED
            && $this->startTime > new DateTimeImmutable();
    }

    /**
     * Check if the meeting is in the past.
     */
    public function isPast(): bool
    {
        return $this->startTime < new DateTimeImmutable();
    }

    /**
     * Get duration in minutes.
     */
    public function durationMinutes(): int
    {
        $diff = $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
        return (int) ($diff / 60);
    }

    // ========== Validation Methods ==========

    private static function validateAttendeeInfo(string $name, string $email): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Attendee name cannot be empty');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid attendee email address');
        }
    }

    private static function validateTimeRange(DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        if ($end <= $start) {
            throw new InvalidArgumentException('End time must be after start time');
        }
    }

    private static function validateTimezone(string $timezone): void
    {
        if (!in_array($timezone, \DateTimeZone::listIdentifiers())) {
            throw new InvalidArgumentException('Invalid timezone: ' . $timezone);
        }
    }

    // ========== AggregateRoot Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(\App\Domain\Shared\Contracts\Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->id !== null && $this->id === $other->id;
    }

    // ========== Getters ==========

    public function meetingTypeId(): int
    {
        return $this->meetingTypeId;
    }

    public function hostUserId(): UserId
    {
        return $this->hostUserId;
    }

    public function contactId(): ?int
    {
        return $this->contactId;
    }

    public function attendeeName(): string
    {
        return $this->attendeeName;
    }

    public function attendeeEmail(): string
    {
        return $this->attendeeEmail;
    }

    public function attendeePhone(): ?string
    {
        return $this->attendeePhone;
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

    public function location(): string
    {
        return $this->location;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function answers(): ?array
    {
        return $this->answers;
    }

    public function status(): MeetingStatus
    {
        return $this->status;
    }

    public function calendarEventId(): ?string
    {
        return $this->calendarEventId;
    }

    public function manageToken(): string
    {
        return $this->manageToken;
    }

    public function reminderSent(): bool
    {
        return $this->reminderSent;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?Timestamp
    {
        return $this->updatedAt;
    }
}
