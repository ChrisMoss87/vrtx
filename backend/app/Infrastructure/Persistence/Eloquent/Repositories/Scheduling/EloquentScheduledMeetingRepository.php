<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Scheduling;

use App\Domain\Scheduling\Entities\ScheduledMeeting;
use App\Domain\Scheduling\Repositories\ScheduledMeetingRepositoryInterface;
use App\Domain\Scheduling\ValueObjects\MeetingStatus;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Models\ScheduledMeeting as ScheduledMeetingModel;
use DateTimeImmutable;

/**
 * Eloquent implementation of the ScheduledMeetingRepository.
 */
class EloquentScheduledMeetingRepository implements ScheduledMeetingRepositoryInterface
{
    public function findById(int $id): ?ScheduledMeeting
    {
        $model = ScheduledMeetingModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByManageToken(string $token): ?ScheduledMeeting
    {
        $model = ScheduledMeetingModel::where('manage_token', $token)->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByHostUserId(UserId $userId): array
    {
        $models = ScheduledMeetingModel::where('host_user_id', $userId->value())
            ->orderBy('start_time', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findUpcomingByHostUserId(UserId $userId): array
    {
        $models = ScheduledMeetingModel::where('host_user_id', $userId->value())
            ->where('status', MeetingStatus::SCHEDULED->value)
            ->where('start_time', '>', now())
            ->orderBy('start_time')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findInRangeForUser(
        UserId $userId,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array {
        $models = ScheduledMeetingModel::where('host_user_id', $userId->value())
            ->where('start_time', '>=', $start->format('Y-m-d H:i:s'))
            ->where('start_time', '<=', $end->format('Y-m-d H:i:s'))
            ->orderBy('start_time')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByStatusForUser(UserId $userId, MeetingStatus $status): array
    {
        $models = ScheduledMeetingModel::where('host_user_id', $userId->value())
            ->where('status', $status->value)
            ->orderBy('start_time', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findNeedingReminder(int $hoursBeforeMeeting = 24): array
    {
        $reminderTime = now()->addHours($hoursBeforeMeeting);

        $models = ScheduledMeetingModel::where('status', MeetingStatus::SCHEDULED->value)
            ->where('reminder_sent', false)
            ->where('start_time', '<=', $reminderTime)
            ->where('start_time', '>', now())
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(ScheduledMeeting $meeting): ScheduledMeeting
    {
        $data = $this->toModelData($meeting);

        if ($meeting->getId() !== null) {
            $model = ScheduledMeetingModel::findOrFail($meeting->getId());
            $model->update($data);
        } else {
            $model = ScheduledMeetingModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = ScheduledMeetingModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    public function hasConflict(
        UserId $userId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?int $excludeMeetingId = null
    ): bool {
        $query = ScheduledMeetingModel::where('host_user_id', $userId->value())
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
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(ScheduledMeetingModel $model): ScheduledMeeting
    {
        return ScheduledMeeting::reconstitute(
            id: $model->id,
            meetingTypeId: $model->meeting_type_id,
            hostUserId: UserId::fromInt($model->host_user_id),
            contactId: $model->contact_id,
            attendeeName: $model->attendee_name,
            attendeeEmail: $model->attendee_email,
            attendeePhone: $model->attendee_phone,
            startTime: new DateTimeImmutable($model->start_time->toIso8601String()),
            endTime: new DateTimeImmutable($model->end_time->toIso8601String()),
            timezone: $model->timezone,
            location: $model->location,
            notes: $model->notes,
            answers: $model->answers,
            status: MeetingStatus::from($model->status),
            calendarEventId: $model->calendar_event_id,
            manageToken: $model->manage_token,
            reminderSent: $model->reminder_sent,
            cancelledAt: $model->cancelled_at ? new DateTimeImmutable($model->cancelled_at->toIso8601String()) : null,
            cancellationReason: $model->cancellation_reason,
            createdAt: $model->created_at ? Timestamp::fromDateTime($model->created_at) : null,
            updatedAt: $model->updated_at ? Timestamp::fromDateTime($model->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(ScheduledMeeting $meeting): array
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
            'answers' => $meeting->answers(),
            'status' => $meeting->status()->value,
            'calendar_event_id' => $meeting->calendarEventId(),
            'manage_token' => $meeting->manageToken(),
            'reminder_sent' => $meeting->reminderSent(),
            'cancelled_at' => $meeting->cancelledAt()?->format('Y-m-d H:i:s'),
            'cancellation_reason' => $meeting->cancellationReason(),
        ];
    }
}
