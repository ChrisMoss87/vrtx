<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for booking a new meeting.
 */
final readonly class CreateMeetingDTO implements JsonSerializable
{
    public function __construct(
        public int $meetingTypeId,
        public string $attendeeName,
        public string $attendeeEmail,
        public DateTimeImmutable $startTime,
        public string $timezone,
        public ?string $attendeePhone = null,
        public ?string $notes = null,
        public ?array $answers = null,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            meetingTypeId: (int) ($data['meeting_type_id'] ?? throw new InvalidArgumentException('Meeting type ID is required')),
            attendeeName: $data['attendee_name'] ?? $data['name'] ?? throw new InvalidArgumentException('Attendee name is required'),
            attendeeEmail: $data['attendee_email'] ?? $data['email'] ?? throw new InvalidArgumentException('Attendee email is required'),
            startTime: isset($data['start_time'])
                ? new DateTimeImmutable($data['start_time'])
                : throw new InvalidArgumentException('Start time is required'),
            timezone: $data['timezone'] ?? 'UTC',
            attendeePhone: $data['attendee_phone'] ?? $data['phone'] ?? null,
            notes: $data['notes'] ?? null,
            answers: $data['answers'] ?? null,
        );
    }

    private function validate(): void
    {
        if (empty(trim($this->attendeeName))) {
            throw new InvalidArgumentException('Attendee name cannot be empty');
        }

        if (!filter_var($this->attendeeEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid attendee email address');
        }

        if (!in_array($this->timezone, \DateTimeZone::listIdentifiers())) {
            throw new InvalidArgumentException('Invalid timezone');
        }

        if ($this->startTime < new DateTimeImmutable()) {
            throw new InvalidArgumentException('Cannot book a meeting in the past');
        }
    }

    public function toArray(): array
    {
        return [
            'meeting_type_id' => $this->meetingTypeId,
            'attendee_name' => $this->attendeeName,
            'attendee_email' => $this->attendeeEmail,
            'start_time' => $this->startTime->format('c'),
            'timezone' => $this->timezone,
            'attendee_phone' => $this->attendeePhone,
            'notes' => $this->notes,
            'answers' => $this->answers,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
