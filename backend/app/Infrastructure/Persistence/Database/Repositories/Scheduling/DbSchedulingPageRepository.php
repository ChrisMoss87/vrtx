<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Scheduling;

use App\Domain\Scheduling\Entities\MeetingType;
use App\Domain\Scheduling\Entities\SchedulingPage;
use App\Domain\Scheduling\Repositories\SchedulingPageRepositoryInterface;
use App\Domain\Scheduling\ValueObjects\LocationType;
use App\Domain\Scheduling\ValueObjects\MeetingDuration;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the SchedulingPageRepository.
 */
class DbSchedulingPageRepository implements SchedulingPageRepositoryInterface
{
    private const TABLE = 'scheduling_pages';
    private const TABLE_MEETING_TYPES = 'meeting_types';
    private const TABLE_USERS = 'users';

    public function findById(int $id): ?SchedulingPage
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $meetingTypes = $this->getMeetingTypesForPage($id);

        return $this->toDomainEntity($row, $meetingTypes);
    }

    public function findBySlug(string $slug): ?SchedulingPage
    {
        $row = DB::table(self::TABLE)->where('slug', $slug)->first();

        if (!$row) {
            return null;
        }

        $meetingTypes = $this->getActiveMeetingTypesForPage((int) $row->id);

        return $this->toDomainEntity($row, $meetingTypes);
    }

    public function findByUserId(UserId $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId->value())
            ->orderBy('name')
            ->get();

        return $rows->map(function ($row) {
            $meetingTypes = $this->getMeetingTypesForPage((int) $row->id);
            return $this->toDomainEntity($row, $meetingTypes);
        })->all();
    }

    public function findActiveByUserId(UserId $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId->value())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $rows->map(function ($row) {
            $meetingTypes = $this->getActiveMeetingTypesForPage((int) $row->id);
            return $this->toDomainEntity($row, $meetingTypes);
        })->all();
    }

    public function save(SchedulingPage $page): SchedulingPage
    {
        $data = $this->toRowData($page);

        if ($page->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $page->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $page->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $meetingTypes = $this->getMeetingTypesForPage($id);
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        return $this->toDomainEntity($row, $meetingTypes);
    }

    public function delete(int $id): bool
    {
        DB::table(self::TABLE_MEETING_TYPES)->where('scheduling_page_id', $id)->delete();

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = DB::table(self::TABLE)->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $meetingTypes = $this->getMeetingTypesForPage($id);
        $user = $row->user_id ? DB::table(self::TABLE_USERS)
            ->where('id', $row->user_id)
            ->select('id', 'name', 'email')
            ->first() : null;

        return $this->toArray($row, $meetingTypes, $user);
    }

    public function findBySlugAsArray(string $slug): ?array
    {
        $row = DB::table(self::TABLE)->where('slug', $slug)->first();

        if (!$row) {
            return null;
        }

        $meetingTypes = $this->getActiveMeetingTypesForPage((int) $row->id);
        $user = $row->user_id ? DB::table(self::TABLE_USERS)
            ->where('id', $row->user_id)
            ->select('id', 'name', 'email')
            ->first() : null;

        return $this->toArray($row, $meetingTypes, $user);
    }

    public function listPaginated(array $filters = [], int $perPage = 25, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter active only
        if (!empty($filters['active'])) {
            $query->where('is_active', true);
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

        // Get total count
        $total = $query->count();

        // Get paginated items
        $offset = ($page - 1) * $perPage;
        $rows = $query->skip($offset)->take($perPage)->get();

        // Convert to arrays
        $items = $rows->map(function ($row) {
            $user = $row->user_id ? DB::table(self::TABLE_USERS)
                ->where('id', $row->user_id)
                ->select('id', 'name', 'email')
                ->first() : null;
            return $this->toArray($row, [], $user);
        })->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    private function getMeetingTypesForPage(int $pageId): array
    {
        $rows = DB::table(self::TABLE_MEETING_TYPES)
            ->where('scheduling_page_id', $pageId)
            ->orderBy('display_order')
            ->get();

        return $rows->map(fn ($row) => $this->meetingTypeToDomainEntity($row))->all();
    }

    private function getActiveMeetingTypesForPage(int $pageId): array
    {
        $rows = DB::table(self::TABLE_MEETING_TYPES)
            ->where('scheduling_page_id', $pageId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return $rows->map(fn ($row) => $this->meetingTypeToDomainEntity($row))->all();
    }

    /**
     * Convert a database row to a domain entity.
     */
    private function toDomainEntity(stdClass $row, array $meetingTypes = []): SchedulingPage
    {
        $page = SchedulingPage::reconstitute(
            id: (int) $row->id,
            userId: UserId::fromInt((int) $row->user_id),
            slug: $row->slug,
            name: $row->name,
            description: $row->description,
            isActive: (bool) $row->is_active,
            timezone: $row->timezone,
            branding: $row->branding ? (is_string($row->branding) ? json_decode($row->branding, true) : $row->branding) : [],
            createdAt: $row->created_at ? Timestamp::fromString($row->created_at) : null,
            updatedAt: $row->updated_at ? Timestamp::fromString($row->updated_at) : null,
        );

        if (!empty($meetingTypes)) {
            $page->setMeetingTypes($meetingTypes);
        }

        return $page;
    }

    /**
     * Convert a MeetingType row to domain entity.
     */
    private function meetingTypeToDomainEntity(stdClass $row): MeetingType
    {
        $settings = $row->settings ? (is_string($row->settings) ? json_decode($row->settings, true) : $row->settings) : [];

        return MeetingType::reconstitute(
            id: (int) $row->id,
            schedulingPageId: (int) $row->scheduling_page_id,
            name: $row->name,
            slug: $row->slug,
            duration: new MeetingDuration((int) $row->duration_minutes),
            description: $row->description,
            locationType: LocationType::from($row->location_type),
            locationDetails: $row->location_details,
            color: $row->color ?? '#3B82F6',
            isActive: (bool) $row->is_active,
            questions: $row->questions ? (is_string($row->questions) ? json_decode($row->questions, true) : $row->questions) : [],
            bufferBefore: $settings['buffer_before'] ?? 0,
            bufferAfter: $settings['buffer_after'] ?? 15,
            minNoticeHours: $settings['min_notice_hours'] ?? 4,
            maxDaysAdvance: $settings['max_days_advance'] ?? 60,
            slotInterval: $settings['slot_interval'] ?? 30,
            displayOrder: (int) ($row->display_order ?? 0),
            createdAt: $row->created_at ? Timestamp::fromString($row->created_at) : null,
            updatedAt: $row->updated_at ? Timestamp::fromString($row->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(SchedulingPage $page): array
    {
        return [
            'user_id' => $page->userId()->value(),
            'slug' => $page->slug(),
            'name' => $page->name(),
            'description' => $page->description(),
            'is_active' => $page->isActive(),
            'timezone' => $page->timezone(),
            'branding' => json_encode($page->branding()),
        ];
    }

    /**
     * Convert a database row to array.
     *
     * @return array<string, mixed>
     */
    private function toArray(stdClass $row, array $meetingTypes = [], ?stdClass $user = null): array
    {
        $data = [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'slug' => $row->slug,
            'name' => $row->name,
            'description' => $row->description,
            'is_active' => (bool) $row->is_active,
            'timezone' => $row->timezone,
            'branding' => $row->branding ? (is_string($row->branding) ? json_decode($row->branding, true) : $row->branding) : [],
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];

        // Include user if loaded
        if ($user) {
            $data['user'] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        }

        // Include meeting types
        if (!empty($meetingTypes)) {
            $data['meeting_types'] = array_map(fn ($type) => $this->meetingTypeToArray($type), $meetingTypes);
        }

        return $data;
    }

    /**
     * Convert a MeetingType entity to array.
     *
     * @return array<string, mixed>
     */
    private function meetingTypeToArray(MeetingType $type): array
    {
        return [
            'id' => $type->id(),
            'scheduling_page_id' => $type->schedulingPageId(),
            'name' => $type->name(),
            'slug' => $type->slug(),
            'duration_minutes' => $type->duration()->minutes(),
            'description' => $type->description(),
            'location_type' => $type->locationType()->value,
            'location_details' => $type->locationDetails(),
            'color' => $type->color(),
            'is_active' => $type->isActive(),
            'questions' => $type->questions(),
            'settings' => [
                'buffer_before' => $type->bufferBefore(),
                'buffer_after' => $type->bufferAfter(),
                'min_notice_hours' => $type->minNoticeHours(),
                'max_days_advance' => $type->maxDaysAdvance(),
                'slot_interval' => $type->slotInterval(),
            ],
            'display_order' => $type->displayOrder(),
            'created_at' => $type->createdAt()?->toDateTimeString(),
            'updated_at' => $type->updatedAt()?->toDateTimeString(),
        ];
    }
}
