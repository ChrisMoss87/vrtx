<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\DTOs;

use App\Domain\Scheduling\Entities\MeetingType;
use JsonSerializable;

/**
 * Data Transfer Object for meeting type responses.
 */
final readonly class MeetingTypeResponseDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $schedulingPageId,
        public string $name,
        public string $slug,
        public int $durationMinutes,
        public ?string $description,
        public string $locationType,
        public ?string $locationDetails,
        public string $color,
        public bool $isActive,
        public array $questions,
        public int $bufferBefore,
        public int $bufferAfter,
        public int $minNoticeHours,
        public int $maxDaysAdvance,
        public int $slotInterval,
        public int $displayOrder,
    ) {}

    public static function fromEntity(MeetingType $meetingType): self
    {
        return new self(
            id: $meetingType->getId(),
            schedulingPageId: $meetingType->schedulingPageId(),
            name: $meetingType->name(),
            slug: $meetingType->slug(),
            durationMinutes: $meetingType->duration()->minutes(),
            description: $meetingType->description(),
            locationType: $meetingType->locationType()->value,
            locationDetails: $meetingType->locationDetails(),
            color: $meetingType->color(),
            isActive: $meetingType->isActive(),
            questions: $meetingType->questions(),
            bufferBefore: $meetingType->bufferBefore(),
            bufferAfter: $meetingType->bufferAfter(),
            minNoticeHours: $meetingType->minNoticeHours(),
            maxDaysAdvance: $meetingType->maxDaysAdvance(),
            slotInterval: $meetingType->slotInterval(),
            displayOrder: $meetingType->displayOrder(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'scheduling_page_id' => $this->schedulingPageId,
            'name' => $this->name,
            'slug' => $this->slug,
            'duration_minutes' => $this->durationMinutes,
            'description' => $this->description,
            'location_type' => $this->locationType,
            'location_details' => $this->locationDetails,
            'color' => $this->color,
            'is_active' => $this->isActive,
            'questions' => $this->questions,
            'buffer_before' => $this->bufferBefore,
            'buffer_after' => $this->bufferAfter,
            'min_notice_hours' => $this->minNoticeHours,
            'max_days_advance' => $this->maxDaysAdvance,
            'slot_interval' => $this->slotInterval,
            'display_order' => $this->displayOrder,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
