<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Events;

use App\Domain\Shared\Events\DomainEvent;
use DateTimeImmutable;

/**
 * Event raised when a meeting is rescheduled.
 */
final class MeetingRescheduled extends DomainEvent
{
    public function __construct(
        private readonly int $meetingId,
        private readonly int $hostUserId,
        private readonly string $attendeeEmail,
        private readonly DateTimeImmutable $oldStartTime,
        private readonly DateTimeImmutable $oldEndTime,
        private readonly DateTimeImmutable $newStartTime,
        private readonly DateTimeImmutable $newEndTime,
        private readonly string $timezone,
        private readonly ?int $rescheduledByUserId,
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

    public function hostUserId(): int
    {
        return $this->hostUserId;
    }

    public function attendeeEmail(): string
    {
        return $this->attendeeEmail;
    }

    public function oldStartTime(): DateTimeImmutable
    {
        return $this->oldStartTime;
    }

    public function oldEndTime(): DateTimeImmutable
    {
        return $this->oldEndTime;
    }

    public function newStartTime(): DateTimeImmutable
    {
        return $this->newStartTime;
    }

    public function newEndTime(): DateTimeImmutable
    {
        return $this->newEndTime;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function rescheduledByUserId(): ?int
    {
        return $this->rescheduledByUserId;
    }

    public function toPayload(): array
    {
        return [
            'meeting_id' => $this->meetingId,
            'host_user_id' => $this->hostUserId,
            'attendee_email' => $this->attendeeEmail,
            'old_start_time' => $this->oldStartTime->format('c'),
            'old_end_time' => $this->oldEndTime->format('c'),
            'new_start_time' => $this->newStartTime->format('c'),
            'new_end_time' => $this->newEndTime->format('c'),
            'timezone' => $this->timezone,
            'rescheduled_by_user_id' => $this->rescheduledByUserId,
        ];
    }
}
