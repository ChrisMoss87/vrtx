<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Scheduling;

use App\Domain\Scheduling\Entities\ScheduledMeeting;
use App\Domain\Scheduling\Repositories\ScheduledMeetingRepositoryInterface;
use App\Domain\Scheduling\ValueObjects\MeetingStatus;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the ScheduledMeetingRepository.
 */
class EloquentScheduledMeetingRepository implements ScheduledMeetingRepositoryInterface
{
    private const TABLE = 'scheduled_meetings';

    public function findById(int $id): ?ScheduledMeeting
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByManageToken(string $token): ?ScheduledMeeting
    {
        $row = DB::table(self::TABLE)->where('manage_token', $token)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByHostUserId(UserId $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('host_user_id', $userId->value())
            ->orderByDesc('start_time')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findUpcomingByHostUserId(UserId $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('host_user_id', $userId->value())
            ->where('status', MeetingStatus::SCHEDULED->value)
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findInRangeForUser(
        UserId $userId,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $rows = DB::table(self::TABLE)
            ->where('host_user_id', $userId->value())
            ->where('start_time', '>=', $start->format('Y-m-d H:i:s'))
            ->where('start_time', '<=', $end->format('Y-m-d H:i:s'))
            ->orderBy('start_time')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findByStatusForUser(UserId $userId, MeetingStatus $status): array
    {
        $rows = DB::table(self::TABLE)
            ->where('host_user_id', $userId->value())
            ->where('status', $status->value)
            ->orderByDesc('start_time')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findNeedingReminder(int $hoursBeforeMeeting = 24): array
    {
        $reminderTime = now()->addHours($hoursBeforeMeeting);

        $rows = DB::table(self::TABLE)
            ->where('status', MeetingStatus::SCHEDULED->value)
            ->where('reminder_sent', false)
            ->where('start_time', '<=', $reminderTime)
            ->where('start_time', '>', now())
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function save(ScheduledMeeting $meeting): ScheduledMeeting
    {
        $data = $this->toRowData($meeting);

        if ($meeting->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $meeting->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $meeting->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function hasConflict(
        UserId $userId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?int $excludeMeetingId = null
    ): bool {
        $query = DB::table(self::TABLE)
            ->where('host_user_id', $userId->value())
            ->where('status', MeetingStatus::SCHEDULED->value)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_time', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
                    ->orWhereBetween('end_time', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_time', '<=', $start->format('Y-m-d H:i:s'))
                            ->where('end_time', '>=', $end->format('Y-m-d H:i:s'));
                    });
            });

        if ($excludeMeetingId !== null) {
            $query->where('id', '!=', $excludeMeetingId);
        }

        return $query->exists();
    }

    /**
     * Convert a database row to a domain entity.
     */
    private function toDomainEntity(stdClass $row): ScheduledMeeting
    {
        return ScheduledMeeting::reconstitute(
            id: (int) $row->id,
            meetingTypeId: (int) $row->meeting_type_id,
            hostUserId: UserId::fromInt((int) $row->host_user_id),
            contactId: $row->contact_id ? (int) $row->contact_id : null,
            attendeeName: $row->attendee_name,
            attendeeEmail: $row->attendee_email,
            attendeePhone: $row->attendee_phone,
            startTime: new DateTimeImmutable($row->start_time),
            endTime: new DateTimeImmutable($row->end_time),
            timezone: $row->timezone,
            location: $row->location,
            notes: $row->notes,
            answers: $row->answers ? (is_string($row->answers) ? json_decode($row->answers, true) : $row->answers) : [],
            status: MeetingStatus::from($row->status),
            calendarEventId: $row->calendar_event_id,
            manageToken: $row->manage_token,
            reminderSent: (bool) $row->reminder_sent,
            cancelledAt: $row->cancelled_at ? new DateTimeImmutable($row->cancelled_at) : null,
            cancellationReason: $row->cancellation_reason,
            createdAt: $row->created_at ? Timestamp::fromString($row->created_at) : null,
            updatedAt: $row->updated_at ? Timestamp::fromString($row->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(ScheduledMeeting $meeting): array
    {
        return [
            'meeting_type_id' => $meeting->meetingTypeId(),
            'host_user_id' => $meeting->hostUserId()->value(),
            'contact_id' => $meeting->contactId(),
            'attendee_name' => $meeting->attendeeName(),
            'attendee_email' => $meeting->attendeeEmail(),
            'attendee_phone' => $meeting->attendeePhone(),
            'start_time' => $meeting->startTime()->format('Y-m-d H:i:s'),
            'end_time' => $meeting->endTime()->format('Y-m-d H:i:s'),
            'timezone' => $meeting->timezone(),
            'location' => $meeting->location(),
            'notes' => $meeting->notes(),
            'answers' => json_encode($meeting->answers()),
            'status' => $meeting->status()->value,
            'calendar_event_id' => $meeting->calendarEventId(),
            'manage_token' => $meeting->manageToken(),
            'reminder_sent' => $meeting->reminderSent(),
            'cancelled_at' => $meeting->cancelledAt()?->format('Y-m-d H:i:s'),
            'cancellation_reason' => $meeting->cancellationReason(),
        ];
    }
}
