<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Scheduling;

use App\Domain\Scheduling\Entities\MeetingType;
use App\Domain\Scheduling\Entities\SchedulingPage;
use App\Domain\Scheduling\Repositories\SchedulingPageRepositoryInterface;
use App\Domain\Scheduling\ValueObjects\LocationType;
use App\Domain\Scheduling\ValueObjects\MeetingDuration;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use App\Models\MeetingType as MeetingTypeModel;
use App\Models\SchedulingPage as SchedulingPageModel;

/**
 * Eloquent implementation of the SchedulingPageRepository.
 */
class EloquentSchedulingPageRepository implements SchedulingPageRepositoryInterface
{
    public function findById(int $id): ?SchedulingPage
    {
        $model = SchedulingPageModel::with('meetingTypes')->find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findBySlug(string $slug): ?SchedulingPage
    {
        $model = SchedulingPageModel::with('activeMeetingTypes')->where('slug', $slug)->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByUserId(UserId $userId): array
    {
        $models = SchedulingPageModel::with('meetingTypes')
            ->where('user_id', $userId->value())
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findActiveByUserId(UserId $userId): array
    {
        $models = SchedulingPageModel::with('activeMeetingTypes')
            ->where('user_id', $userId->value())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(SchedulingPage $page): SchedulingPage
    {
        $data = $this->toModelData($page);

        if ($page->getId() !== null) {
            $model = SchedulingPageModel::findOrFail($page->getId());
            $model->update($data);
        } else {
            $model = SchedulingPageModel::create($data);
        }

        return $this->toDomainEntity($model->fresh(['meetingTypes']));
    }

    public function delete(int $id): bool
    {
        $model = SchedulingPageModel::find($id);

        if (!$model) {
            return false;
        }

        $model->meetingTypes()->delete();
        return $model->delete() ?? false;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = SchedulingPageModel::where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(SchedulingPageModel $model): SchedulingPage
    {
        $page = SchedulingPage::reconstitute(
            id: $model->id,
            userId: UserId::fromInt($model->user_id),
            slug: $model->slug,
            name: $model->name,
            description: $model->description,
            isActive: $model->is_active,
            timezone: $model->timezone,
            branding: $model->branding ?? [],
            createdAt: $model->created_at ? Timestamp::fromDateTime($model->created_at) : null,
            updatedAt: $model->updated_at ? Timestamp::fromDateTime($model->updated_at) : null,
        );

        // Add meeting types if loaded
        if ($model->relationLoaded('meetingTypes') || $model->relationLoaded('activeMeetingTypes')) {
            $types = $model->meetingTypes ?? $model->activeMeetingTypes ?? collect();
            $meetingTypes = $types->map(fn($t) => $this->meetingTypeToDomainEntity($t))->all();
            $page->setMeetingTypes($meetingTypes);
        }

        return $page;
    }

    /**
     * Convert a MeetingType model to domain entity.
     */
    private function meetingTypeToDomainEntity(MeetingTypeModel $model): MeetingType
    {
        return MeetingType::reconstitute(
            id: $model->id,
            schedulingPageId: $model->scheduling_page_id,
            name: $model->name,
            slug: $model->slug,
            duration: new MeetingDuration($model->duration_minutes),
            description: $model->description,
            locationType: LocationType::from($model->location_type),
            locationDetails: $model->location_details,
            color: $model->color ?? '#3B82F6',
            isActive: $model->is_active,
            questions: $model->questions ?? [],
            bufferBefore: $model->settings['buffer_before'] ?? 0,
            bufferAfter: $model->settings['buffer_after'] ?? 15,
            minNoticeHours: $model->settings['min_notice_hours'] ?? 4,
            maxDaysAdvance: $model->settings['max_days_advance'] ?? 60,
            slotInterval: $model->settings['slot_interval'] ?? 30,
            displayOrder: $model->display_order ?? 0,
            createdAt: $model->created_at ? Timestamp::fromDateTime($model->created_at) : null,
            updatedAt: $model->updated_at ? Timestamp::fromDateTime($model->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(SchedulingPage $page): array
    {
        return [
            'user_id' => $page->userId()->value(),
            'slug' => $page->slug(),
            'name' => $page->name(),
            'description' => $page->description(),
            'is_active' => $page->isActive(),
            'timezone' => $page->timezone(),
            'branding' => $page->branding(),
        ];
    }
}
