<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Repositories;

use App\Domain\Scheduling\Entities\ScheduledMeeting;
use App\Domain\Scheduling\ValueObjects\MeetingStatus;
use App\Domain\Shared\ValueObjects\UserId;
use DateTimeImmutable;

/**
 * Repository interface for ScheduledMeeting aggregate root.
 */
interface ScheduledMeetingRepositoryInterface
{
    /**
     * Find a scheduled meeting by its ID.
     */
    public function findById(int $id): ?ScheduledMeeting;

    /**
     * Find a scheduled meeting by its manage token.
     */
    public function findByManageToken(string $token): ?ScheduledMeeting;

    /**
     * Find scheduled meetings for a host user.
     *
     * @return array<ScheduledMeeting>
     */
    public function findByHostUserId(UserId $userId): array;

    /**
     * Find upcoming scheduled meetings for a host user.
     *
     * @return array<ScheduledMeeting>
     */
    public function findUpcomingByHostUserId(UserId $userId): array;

    /**
     * Find scheduled meetings in a date range for a user.
     *
     * @return array<ScheduledMeeting>
     */
    public function findInRangeForUser(
        UserId $userId,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): array;

    /**
     * Find meetings by status for a user.
     *
     * @return array<ScheduledMeeting>
     */
    public function findByStatusForUser(UserId $userId, MeetingStatus $status): array;

    /**
     * Find meetings needing reminders.
     *
     * @return array<ScheduledMeeting>
     */
    public function findNeedingReminder(int $hoursBeforeMeeting = 24): array;

    /**
     * Save a scheduled meeting (insert or update).
     */
    public function save(ScheduledMeeting $meeting): ScheduledMeeting;

    /**
     * Delete a scheduled meeting.
     */
    public function delete(int $id): bool;

    /**
     * Check if a time slot has a conflicting meeting for a user.
     */
    public function hasConflict(
        UserId $userId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?int $excludeMeetingId = null
    ): bool;
}
