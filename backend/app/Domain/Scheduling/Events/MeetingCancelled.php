<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Events;

use App\Domain\Shared\Events\DomainEvent;

/**
 * Event raised when a meeting is cancelled.
 */
final class MeetingCancelled extends DomainEvent
{
    public function __construct(
        private readonly int $meetingId,
        private readonly int $hostUserId,
        private readonly string $attendeeEmail,
        private readonly ?string $reason,
        private readonly ?int $cancelledByUserId,
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

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function cancelledByUserId(): ?int
    {
        return $this->cancelledByUserId;
    }

    public function toPayload(): array
    {
        return [
            'meeting_id' => $this->meetingId,
            'host_user_id' => $this->hostUserId,
            'attendee_email' => $this->attendeeEmail,
            'reason' => $this->reason,
            'cancelled_by_user_id' => $this->cancelledByUserId,
        ];
    }
}
