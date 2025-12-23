<?php

declare(strict_types=1);

namespace App\Application\Services\Scheduling;

use App\Domain\Scheduling\Repositories\ScheduledMeetingRepositoryInterface;
use App\Domain\Scheduling\Repositories\SchedulingPageRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Shared\ValueObjects\UserId;

class SchedulingApplicationService
{
    public function __construct(
        private SchedulingPageRepositoryInterface $schedulingPageRepository,
        private ScheduledMeetingRepositoryInterface $scheduledMeetingRepository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // QUERY USE CASES - SCHEDULING PAGES
    // =========================================================================

    /**
     * List scheduling pages with filtering.
     */
    public function listSchedulingPages(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        return $this->schedulingPageRepository->listPaginated($filters, $perPage, $page);
    }

    /**
     * Get a scheduling page by ID.
     */
    public function getSchedulingPage(int $id): ?array
    {
        return $this->schedulingPageRepository->findByIdAsArray($id);
    }

    /**
     * Get a scheduling page by slug.
     */
    public function getSchedulingPageBySlug(string $slug): ?array
    {
        return $this->schedulingPageRepository->findBySlugAsArray($slug);
    }

    // =========================================================================
    // COMMAND USE CASES - SCHEDULING PAGES
    // =========================================================================

    /**
     * Create a new scheduling page.
     */
    public function createSchedulingPage(array $data): array
    {
        $userId = $data['user_id'] ?? $this->authContext->userId();
        if ($userId === null) {
            throw new \InvalidArgumentException('User ID is required to create a scheduling page');
        }

        $page = \App\Domain\Scheduling\Entities\SchedulingPage::create(
            userId: UserId::fromInt($userId),
            slug: $data['slug'],
            name: $data['name'],
            timezone: $data['timezone'] ?? config('app.timezone'),
            description: $data['description'] ?? null,
            branding: $data['branding'] ?? [],
        );

        if (isset($data['is_active']) && !$data['is_active']) {
            $page->deactivate();
        }

        $savedPage = $this->schedulingPageRepository->save($page);

        return $this->schedulingPageRepository->findByIdAsArray($savedPage->getId());
    }

    /**
     * Update a scheduling page.
     */
    public function updateSchedulingPage(int $id, array $data): array
    {
        $page = $this->schedulingPageRepository->findById($id);

        if (!$page) {
            throw new \InvalidArgumentException("Scheduling page with ID {$id} not found");
        }

        $page->update(
            name: $data['name'] ?? $page->name(),
            description: $data['description'] ?? $page->description(),
            timezone: $data['timezone'] ?? $page->timezone(),
            branding: isset($data['branding'])
                ? array_merge($page->branding(), $data['branding'])
                : $page->branding(),
        );

        if (isset($data['is_active'])) {
            if ($data['is_active']) {
                $page->activate();
            } else {
                $page->deactivate();
            }
        }

        $this->schedulingPageRepository->save($page);

        return $this->schedulingPageRepository->findByIdAsArray($id);
    }

    /**
     * Delete a scheduling page.
     */
    public function deleteSchedulingPage(int $id): bool
    {
        return $this->schedulingPageRepository->delete($id);
    }

    // =========================================================================
    // QUERY USE CASES - MEETING TYPES
    // =========================================================================
    // TODO: Implement MeetingTypeRepositoryInterface and refactor these methods

    /**
     * List meeting types for a scheduling page.
     */
    public function listMeetingTypes(int $schedulingPageId): array
    {
        // TODO: Move to MeetingTypeRepository
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Get a meeting type by ID.
     */
    public function getMeetingType(int $id): ?array
    {
        // TODO: Move to MeetingTypeRepository
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Get meeting type by slug within a scheduling page.
     */
    public function getMeetingTypeBySlug(string $pageSlug, string $typeSlug): ?array
    {
        // TODO: Move to MeetingTypeRepository
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    // =========================================================================
    // COMMAND USE CASES - MEETING TYPES
    // =========================================================================
    // TODO: Implement MeetingTypeRepositoryInterface and refactor these methods

    /**
     * Create a new meeting type.
     */
    public function createMeetingType(int $schedulingPageId, array $data): array
    {
        // TODO: Move to MeetingTypeRepository
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Update a meeting type.
     */
    public function updateMeetingType(int $id, array $data): array
    {
        // TODO: Move to MeetingTypeRepository
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Delete a meeting type.
     */
    public function deleteMeetingType(int $id): bool
    {
        // TODO: Move to MeetingTypeRepository
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    // =========================================================================
    // QUERY USE CASES - SCHEDULED MEETINGS
    // =========================================================================
    // TODO: Add pagination and array conversion methods to ScheduledMeetingRepositoryInterface

    /**
     * List scheduled meetings with filtering.
     */
    public function listMeetings(array $filters = [], int $perPage = 25): PaginatedResult
    {
        // TODO: Add listPaginated method to ScheduledMeetingRepositoryInterface
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Get a scheduled meeting by ID.
     */
    public function getMeeting(int $id): ?array
    {
        // TODO: Add findByIdAsArray method to ScheduledMeetingRepositoryInterface
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Get a meeting by manage token.
     */
    public function getMeetingByToken(string $token): ?array
    {
        $meeting = $this->scheduledMeetingRepository->findByManageToken($token);
        if (!$meeting) {
            return null;
        }
        // TODO: Add toArray conversion in repository
        throw new \BadMethodCallException('Method not yet refactored to DDD - needs array conversion');
    }

    /**
     * Get upcoming meetings for a host.
     */
    public function getUpcomingMeetings(?int $userId = null, int $days = 7): array
    {
        $userId = $userId ?? $this->authContext->userId();
        if ($userId === null) {
            return [];
        }

        $meetings = $this->scheduledMeetingRepository->findUpcomingByHostUserId(UserId::fromInt($userId));
        // TODO: Filter by days and convert to array
        throw new \BadMethodCallException('Method not yet refactored to DDD - needs array conversion');
    }

    /**
     * Get meetings needing reminders.
     */
    public function getMeetingsNeedingReminder(int $hoursBeforeMeeting = 24): array
    {
        $meetings = $this->scheduledMeetingRepository->findNeedingReminder($hoursBeforeMeeting);
        // TODO: Convert to array
        throw new \BadMethodCallException('Method not yet refactored to DDD - needs array conversion');
    }

    // =========================================================================
    // COMMAND USE CASES - SCHEDULED MEETINGS
    // =========================================================================
    // TODO: Refactor to use domain entities and repositories

    /**
     * Book a new meeting.
     */
    public function bookMeeting(array $data): array
    {
        // TODO: Refactor to use ScheduledMeeting domain entity and repositories
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Reschedule a meeting.
     */
    public function rescheduleMeeting(int $id, array $data): array
    {
        // TODO: Refactor to use ScheduledMeeting domain entity and repositories
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Cancel a meeting.
     */
    public function cancelMeeting(int $id, ?string $reason = null): array
    {
        // TODO: Refactor to use ScheduledMeeting domain entity and repositories
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Mark meeting as completed.
     */
    public function completeMeeting(int $id): array
    {
        // TODO: Refactor to use ScheduledMeeting domain entity and repositories
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Mark meeting as no-show.
     */
    public function markNoShow(int $id): array
    {
        // TODO: Refactor to use ScheduledMeeting domain entity and repositories
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Mark reminder as sent.
     */
    public function markReminderSent(int $id): array
    {
        // TODO: Refactor to use ScheduledMeeting domain entity and repositories
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    // =========================================================================
    // QUERY USE CASES - AVAILABILITY
    // =========================================================================
    // TODO: Implement AvailabilityRule and SchedulingOverride repositories

    /**
     * Get availability rules for a user.
     */
    public function getAvailabilityRules(int $userId): array
    {
        // TODO: Implement AvailabilityRuleRepositoryInterface
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Get scheduling overrides for a user in a date range.
     */
    public function getSchedulingOverrides(int $userId, string $startDate, string $endDate): array
    {
        // TODO: Implement SchedulingOverrideRepositoryInterface
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    // =========================================================================
    // COMMAND USE CASES - AVAILABILITY
    // =========================================================================
    // TODO: Implement AvailabilityRule and SchedulingOverride repositories

    /**
     * Set availability rules for a user.
     */
    public function setAvailabilityRules(int $userId, array $rules): array
    {
        // TODO: Implement AvailabilityRuleRepositoryInterface
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Add availability override for a specific date.
     */
    public function addSchedulingOverride(int $userId, array $data): array
    {
        // TODO: Implement SchedulingOverrideRepositoryInterface
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    /**
     * Delete an availability override.
     */
    public function deleteSchedulingOverride(int $id): bool
    {
        // TODO: Implement SchedulingOverrideRepositoryInterface
        throw new \BadMethodCallException('Method not yet refactored to DDD');
    }

    // =========================================================================
    // ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get meeting statistics for a host.
     */
    public function getMeetingStats(?int $userId = null, int $days = 30): array
    {
        $query = ScheduledMeeting::query();

        if ($userId) {
            $query->forHost($userId);
        }

        $query->where('start_time', '>=', now()->subDays($days));

        $total = $query->count();
        $scheduled = (clone $query)->where('status', ScheduledMeeting::STATUS_SCHEDULED)->count();
        $completed = (clone $query)->where('status', ScheduledMeeting::STATUS_COMPLETED)->count();
        $cancelled = (clone $query)->where('status', ScheduledMeeting::STATUS_CANCELLED)->count();
        $noShow = (clone $query)->where('status', ScheduledMeeting::STATUS_NO_SHOW)->count();

        $upcoming = ScheduledMeeting::upcoming()
            ->when($userId, fn($q) => $q->forHost($userId))
            ->count();

        return [
            'total' => $total,
            'scheduled' => $scheduled,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'no_show' => $noShow,
            'upcoming' => $upcoming,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'cancellation_rate' => $total > 0 ? round(($cancelled / $total) * 100, 2) : 0,
            'no_show_rate' => $total > 0 ? round(($noShow / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get meeting type analytics.
     */
    public function getMeetingTypeStats(int $meetingTypeId, int $days = 30): array
    {
        $query = ScheduledMeeting::where('meeting_type_id', $meetingTypeId)
            ->where('start_time', '>=', now()->subDays($days));

        $total = $query->count();
        $completed = (clone $query)->where('status', ScheduledMeeting::STATUS_COMPLETED)->count();
        $cancelled = (clone $query)->where('status', ScheduledMeeting::STATUS_CANCELLED)->count();
        $noShow = (clone $query)->where('status', ScheduledMeeting::STATUS_NO_SHOW)->count();

        $avgDuration = (clone $query)
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration')
            ->value('avg_duration');

        return [
            'total_meetings' => $total,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'no_show' => $noShow,
            'average_duration_minutes' => $avgDuration ? round($avgDuration, 2) : 0,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get daily meeting counts for dashboard.
     */
    public function getDailyMeetingCounts(?int $userId = null, int $days = 30): Collection
    {
        $query = ScheduledMeeting::query()
            ->selectRaw('DATE(start_time) as date, COUNT(*) as count')
            ->where('start_time', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date');

        if ($userId) {
            $query->forHost($userId);
        }

        return $query->get();
    }

    /**
     * Get popular meeting times.
     */
    public function getPopularMeetingTimes(?int $userId = null, int $days = 30): Collection
    {
        $query = ScheduledMeeting::query()
            ->selectRaw('HOUR(start_time) as hour, COUNT(*) as count')
            ->where('start_time', '>=', now()->subDays($days))
            ->where('status', ScheduledMeeting::STATUS_COMPLETED)
            ->groupBy('hour')
            ->orderBy('count', 'desc');

        if ($userId) {
            $query->forHost($userId);
        }

        return $query->get();
    }
}
