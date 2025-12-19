<?php

declare(strict_types=1);

namespace App\Application\Services\Scheduling;

use App\Domain\Scheduling\Repositories\ScheduledMeetingRepositoryInterface;
use App\Models\ScheduledMeeting;
use App\Models\SchedulingPage;
use App\Models\MeetingType;
use App\Models\AvailabilityRule;
use App\Models\SchedulingOverride;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SchedulingApplicationService
{
    public function __construct(
        private ScheduledMeetingRepositoryInterface $repository,
    ) {}

    // =========================================================================
    // QUERY USE CASES - SCHEDULING PAGES
    // =========================================================================

    /**
     * List scheduling pages with filtering.
     */
    public function listSchedulingPages(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = SchedulingPage::query()
            ->with(['user:id,name,email']);

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter active only
        if (!empty($filters['active'])) {
            $query->active();
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a scheduling page by ID.
     */
    public function getSchedulingPage(int $id): ?SchedulingPage
    {
        return SchedulingPage::with(['user:id,name,email', 'meetingTypes'])->find($id);
    }

    /**
     * Get a scheduling page by slug.
     */
    public function getSchedulingPageBySlug(string $slug): ?SchedulingPage
    {
        return SchedulingPage::with(['activeMeetingTypes', 'user:id,name,email'])
            ->where('slug', $slug)
            ->first();
    }

    // =========================================================================
    // COMMAND USE CASES - SCHEDULING PAGES
    // =========================================================================

    /**
     * Create a new scheduling page.
     */
    public function createSchedulingPage(array $data): SchedulingPage
    {
        return SchedulingPage::create([
            'user_id' => $data['user_id'] ?? Auth::id(),
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'timezone' => $data['timezone'] ?? config('app.timezone'),
            'branding' => $data['branding'] ?? [],
        ]);
    }

    /**
     * Update a scheduling page.
     */
    public function updateSchedulingPage(int $id, array $data): SchedulingPage
    {
        $page = SchedulingPage::findOrFail($id);

        $page->update([
            'name' => $data['name'] ?? $page->name,
            'description' => $data['description'] ?? $page->description,
            'is_active' => $data['is_active'] ?? $page->is_active,
            'timezone' => $data['timezone'] ?? $page->timezone,
            'branding' => array_merge($page->branding ?? [], $data['branding'] ?? []),
        ]);

        return $page->fresh();
    }

    /**
     * Delete a scheduling page.
     */
    public function deleteSchedulingPage(int $id): bool
    {
        $page = SchedulingPage::findOrFail($id);
        return $page->delete();
    }

    // =========================================================================
    // QUERY USE CASES - MEETING TYPES
    // =========================================================================

    /**
     * List meeting types for a scheduling page.
     */
    public function listMeetingTypes(int $schedulingPageId): Collection
    {
        return MeetingType::where('scheduling_page_id', $schedulingPageId)
            ->with('schedulingPage:id,name,slug')
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Get a meeting type by ID.
     */
    public function getMeetingType(int $id): ?MeetingType
    {
        return MeetingType::with(['schedulingPage', 'scheduledMeetings'])->find($id);
    }

    /**
     * Get meeting type by slug within a scheduling page.
     */
    public function getMeetingTypeBySlug(string $pageSlug, string $typeSlug): ?MeetingType
    {
        return MeetingType::whereHas('schedulingPage', function ($q) use ($pageSlug) {
            $q->where('slug', $pageSlug);
        })
            ->where('slug', $typeSlug)
            ->with('schedulingPage')
            ->first();
    }

    // =========================================================================
    // COMMAND USE CASES - MEETING TYPES
    // =========================================================================

    /**
     * Create a new meeting type.
     */
    public function createMeetingType(int $schedulingPageId, array $data): MeetingType
    {
        return MeetingType::create([
            'scheduling_page_id' => $schedulingPageId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? 30,
            'description' => $data['description'] ?? null,
            'location_type' => $data['location_type'] ?? 'video',
            'location_details' => $data['location_details'] ?? null,
            'color' => $data['color'] ?? '#3B82F6',
            'is_active' => $data['is_active'] ?? true,
            'questions' => $data['questions'] ?? [],
            'settings' => $data['settings'] ?? MeetingType::DEFAULT_SETTINGS,
            'display_order' => $data['display_order'] ?? 0,
        ]);
    }

    /**
     * Update a meeting type.
     */
    public function updateMeetingType(int $id, array $data): MeetingType
    {
        $type = MeetingType::findOrFail($id);

        $type->update([
            'name' => $data['name'] ?? $type->name,
            'duration_minutes' => $data['duration_minutes'] ?? $type->duration_minutes,
            'description' => $data['description'] ?? $type->description,
            'location_type' => $data['location_type'] ?? $type->location_type,
            'location_details' => $data['location_details'] ?? $type->location_details,
            'color' => $data['color'] ?? $type->color,
            'is_active' => $data['is_active'] ?? $type->is_active,
            'questions' => $data['questions'] ?? $type->questions,
            'settings' => array_merge($type->settings ?? [], $data['settings'] ?? []),
            'display_order' => $data['display_order'] ?? $type->display_order,
        ]);

        return $type->fresh();
    }

    /**
     * Delete a meeting type.
     */
    public function deleteMeetingType(int $id): bool
    {
        $type = MeetingType::findOrFail($id);
        return $type->delete();
    }

    // =========================================================================
    // QUERY USE CASES - SCHEDULED MEETINGS
    // =========================================================================

    /**
     * List scheduled meetings with filtering.
     */
    public function listMeetings(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = ScheduledMeeting::query()
            ->with(['host:id,name,email', 'meetingType:id,name,duration_minutes', 'contact']);

        // Filter by host
        if (!empty($filters['host_user_id'])) {
            $query->forHost($filters['host_user_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by meeting type
        if (!empty($filters['meeting_type_id'])) {
            $query->where('meeting_type_id', $filters['meeting_type_id']);
        }

        // Filter by date range
        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->inRange($filters['from_date'], $filters['to_date']);
        }

        // Filter upcoming only
        if (!empty($filters['upcoming'])) {
            $query->upcoming();
        }

        // Filter scheduled only
        if (!empty($filters['scheduled'])) {
            $query->scheduled();
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('attendee_name', 'like', "%{$search}%")
                    ->orWhere('attendee_email', 'like', "%{$search}%")
                    ->orWhere('attendee_phone', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'start_time';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a scheduled meeting by ID.
     */
    public function getMeeting(int $id): ?ScheduledMeeting
    {
        return ScheduledMeeting::with(['host:id,name,email', 'meetingType', 'contact'])->find($id);
    }

    /**
     * Get a meeting by manage token.
     */
    public function getMeetingByToken(string $token): ?ScheduledMeeting
    {
        return ScheduledMeeting::with(['host:id,name,email', 'meetingType'])
            ->where('manage_token', $token)
            ->first();
    }

    /**
     * Get upcoming meetings for a host.
     */
    public function getUpcomingMeetings(?int $userId = null, int $days = 7): Collection
    {
        $query = ScheduledMeeting::upcoming()
            ->with(['host:id,name,email', 'meetingType', 'contact'])
            ->where('start_time', '<=', now()->addDays($days))
            ->orderBy('start_time');

        if ($userId) {
            $query->forHost($userId);
        }

        return $query->get();
    }

    /**
     * Get meetings needing reminders.
     */
    public function getMeetingsNeedingReminder(int $hoursBeforeMeeting = 24): Collection
    {
        return ScheduledMeeting::needingReminder($hoursBeforeMeeting)
            ->with(['host:id,name,email', 'meetingType'])
            ->get();
    }

    // =========================================================================
    // COMMAND USE CASES - SCHEDULED MEETINGS
    // =========================================================================

    /**
     * Book a new meeting.
     */
    public function bookMeeting(array $data): ScheduledMeeting
    {
        $meetingType = MeetingType::findOrFail($data['meeting_type_id']);

        return DB::transaction(function () use ($data, $meetingType) {
            $meeting = ScheduledMeeting::create([
                'meeting_type_id' => $data['meeting_type_id'],
                'host_user_id' => $data['host_user_id'] ?? $meetingType->schedulingPage->user_id,
                'contact_id' => $data['contact_id'] ?? null,
                'attendee_name' => $data['attendee_name'],
                'attendee_email' => $data['attendee_email'],
                'attendee_phone' => $data['attendee_phone'] ?? null,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'timezone' => $data['timezone'] ?? $meetingType->schedulingPage->timezone,
                'location' => $data['location'] ?? $meetingType->location_details,
                'notes' => $data['notes'] ?? null,
                'answers' => $data['answers'] ?? [],
                'status' => ScheduledMeeting::STATUS_SCHEDULED,
            ]);

            // TODO: Send confirmation email
            // TODO: Create calendar event
            // TODO: Notify host

            return $meeting->fresh(['host', 'meetingType']);
        });
    }

    /**
     * Reschedule a meeting.
     */
    public function rescheduleMeeting(int $id, array $data): ScheduledMeeting
    {
        $meeting = ScheduledMeeting::findOrFail($id);

        if (!$meeting->can_reschedule) {
            throw new \InvalidArgumentException('Meeting cannot be rescheduled');
        }

        return DB::transaction(function () use ($meeting, $data) {
            $meeting->update([
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'status' => ScheduledMeeting::STATUS_RESCHEDULED,
                'reminder_sent' => false, // Reset reminder flag
            ]);

            // TODO: Send reschedule notification
            // TODO: Update calendar event

            return $meeting->fresh(['host', 'meetingType']);
        });
    }

    /**
     * Cancel a meeting.
     */
    public function cancelMeeting(int $id, ?string $reason = null): ScheduledMeeting
    {
        $meeting = ScheduledMeeting::findOrFail($id);

        if (!$meeting->can_cancel) {
            throw new \InvalidArgumentException('Meeting cannot be cancelled');
        }

        return DB::transaction(function () use ($meeting, $reason) {
            $meeting->cancel($reason);

            // TODO: Send cancellation notification
            // TODO: Delete calendar event

            return $meeting->fresh(['host', 'meetingType']);
        });
    }

    /**
     * Mark meeting as completed.
     */
    public function completeMeeting(int $id): ScheduledMeeting
    {
        $meeting = ScheduledMeeting::findOrFail($id);
        $meeting->markCompleted();

        return $meeting->fresh(['host', 'meetingType']);
    }

    /**
     * Mark meeting as no-show.
     */
    public function markNoShow(int $id): ScheduledMeeting
    {
        $meeting = ScheduledMeeting::findOrFail($id);
        $meeting->markNoShow();

        return $meeting->fresh(['host', 'meetingType']);
    }

    /**
     * Mark reminder as sent.
     */
    public function markReminderSent(int $id): ScheduledMeeting
    {
        $meeting = ScheduledMeeting::findOrFail($id);
        $meeting->update(['reminder_sent' => true]);

        return $meeting->fresh();
    }

    // =========================================================================
    // QUERY USE CASES - AVAILABILITY
    // =========================================================================

    /**
     * Get availability rules for a user.
     */
    public function getAvailabilityRules(int $userId): Collection
    {
        return AvailabilityRule::where('user_id', $userId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Get scheduling overrides for a user in a date range.
     */
    public function getSchedulingOverrides(int $userId, string $startDate, string $endDate): Collection
    {
        return SchedulingOverride::where('user_id', $userId)
            ->inRange($startDate, $endDate)
            ->orderBy('date')
            ->get();
    }

    // =========================================================================
    // COMMAND USE CASES - AVAILABILITY
    // =========================================================================

    /**
     * Set availability rules for a user.
     */
    public function setAvailabilityRules(int $userId, array $rules): Collection
    {
        return DB::transaction(function () use ($userId, $rules) {
            // Delete existing rules
            AvailabilityRule::where('user_id', $userId)->delete();

            // Create new rules
            foreach ($rules as $rule) {
                AvailabilityRule::create([
                    'user_id' => $userId,
                    'day_of_week' => $rule['day_of_week'],
                    'start_time' => $rule['start_time'],
                    'end_time' => $rule['end_time'],
                    'is_available' => $rule['is_available'] ?? true,
                ]);
            }

            return AvailabilityRule::where('user_id', $userId)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();
        });
    }

    /**
     * Add availability override for a specific date.
     */
    public function addSchedulingOverride(int $userId, array $data): SchedulingOverride
    {
        return SchedulingOverride::create([
            'user_id' => $userId,
            'date' => $data['date'],
            'is_available' => $data['is_available'] ?? false,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);
    }

    /**
     * Delete an availability override.
     */
    public function deleteSchedulingOverride(int $id): bool
    {
        $override = SchedulingOverride::findOrFail($id);
        return $override->delete();
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
