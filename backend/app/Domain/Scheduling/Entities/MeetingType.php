<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Entities;

use App\Domain\Scheduling\ValueObjects\LocationType;
use App\Domain\Scheduling\ValueObjects\MeetingDuration;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\ValueObjects\Timestamp;
use InvalidArgumentException;

/**
 * MeetingType entity.
 *
 * Represents a type of meeting that can be booked on a scheduling page.
 */
final class MeetingType implements Entity
{
    private function __construct(
        private ?int $id,
        private int $schedulingPageId,
        private string $name,
        private string $slug,
        private MeetingDuration $duration,
        private ?string $description,
        private LocationType $locationType,
        private ?string $locationDetails,
        private string $color,
        private bool $isActive,
        private array $questions,
        private int $bufferBefore,
        private int $bufferAfter,
        private int $minNoticeHours,
        private int $maxDaysAdvance,
        private int $slotInterval,
        private int $displayOrder,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a new meeting type.
     */
    public static function create(
        int $schedulingPageId,
        string $name,
        string $slug,
        MeetingDuration $duration,
        LocationType $locationType,
        ?string $description = null,
        ?string $locationDetails = null,
        string $color = '#3B82F6',
        array $questions = [],
        int $bufferBefore = 0,
        int $bufferAfter = 15,
        int $minNoticeHours = 4,
        int $maxDaysAdvance = 60,
        int $slotInterval = 30,
        int $displayOrder = 0,
    ): self {
        self::validateName($name);
        self::validateSlug($slug);
        self::validateColor($color);
        self::validateBufferTimes($bufferBefore, $bufferAfter);
        self::validateSchedulingLimits($minNoticeHours, $maxDaysAdvance, $slotInterval);

        return new self(
            id: null,
            schedulingPageId: $schedulingPageId,
            name: $name,
            slug: $slug,
            duration: $duration,
            description: $description,
            locationType: $locationType,
            locationDetails: $locationDetails,
            color: $color,
            isActive: true,
            questions: $questions,
            bufferBefore: $bufferBefore,
            bufferAfter: $bufferAfter,
            minNoticeHours: $minNoticeHours,
            maxDaysAdvance: $maxDaysAdvance,
            slotInterval: $slotInterval,
            displayOrder: $displayOrder,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        int $schedulingPageId,
        string $name,
        string $slug,
        MeetingDuration $duration,
        ?string $description,
        LocationType $locationType,
        ?string $locationDetails,
        string $color,
        bool $isActive,
        array $questions,
        int $bufferBefore,
        int $bufferAfter,
        int $minNoticeHours,
        int $maxDaysAdvance,
        int $slotInterval,
        int $displayOrder,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            schedulingPageId: $schedulingPageId,
            name: $name,
            slug: $slug,
            duration: $duration,
            description: $description,
            locationType: $locationType,
            locationDetails: $locationDetails,
            color: $color,
            isActive: $isActive,
            questions: $questions,
            bufferBefore: $bufferBefore,
            bufferAfter: $bufferAfter,
            minNoticeHours: $minNoticeHours,
            maxDaysAdvance: $maxDaysAdvance,
            slotInterval: $slotInterval,
            displayOrder: $displayOrder,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update meeting type details.
     */
    public function update(
        string $name,
        MeetingDuration $duration,
        LocationType $locationType,
        ?string $description = null,
        ?string $locationDetails = null,
        string $color = '#3B82F6',
        array $questions = [],
    ): void {
        self::validateName($name);
        self::validateColor($color);

        $this->name = $name;
        $this->duration = $duration;
        $this->locationType = $locationType;
        $this->description = $description;
        $this->locationDetails = $locationDetails;
        $this->color = $color;
        $this->questions = $questions;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Update scheduling settings.
     */
    public function updateSchedulingSettings(
        int $bufferBefore,
        int $bufferAfter,
        int $minNoticeHours,
        int $maxDaysAdvance,
        int $slotInterval,
    ): void {
        self::validateBufferTimes($bufferBefore, $bufferAfter);
        self::validateSchedulingLimits($minNoticeHours, $maxDaysAdvance, $slotInterval);

        $this->bufferBefore = $bufferBefore;
        $this->bufferAfter = $bufferAfter;
        $this->minNoticeHours = $minNoticeHours;
        $this->maxDaysAdvance = $maxDaysAdvance;
        $this->slotInterval = $slotInterval;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Activate the meeting type.
     */
    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Deactivate the meeting type.
     */
    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Update display order.
     */
    public function updateDisplayOrder(int $order): void
    {
        $this->displayOrder = $order;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Get total blocked time including buffers.
     */
    public function totalBlockedMinutes(): int
    {
        return $this->bufferBefore + $this->duration->minutes() + $this->bufferAfter;
    }

    /**
     * Get the location string for display.
     */
    public function locationString(): string
    {
        if ($this->locationDetails) {
            return $this->locationDetails;
        }

        return $this->locationType->defaultLocationText();
    }

    // ========== Validation Methods ==========

    private static function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Meeting type name cannot be empty');
        }

        if (strlen($name) > 255) {
            throw new InvalidArgumentException('Meeting type name cannot exceed 255 characters');
        }
    }

    private static function validateSlug(string $slug): void
    {
        if (empty(trim($slug))) {
            throw new InvalidArgumentException('Slug cannot be empty');
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new InvalidArgumentException('Slug can only contain lowercase letters, numbers, and hyphens');
        }
    }

    private static function validateColor(string $color): void
    {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new InvalidArgumentException('Color must be a valid hex color (e.g., #3B82F6)');
        }
    }

    private static function validateBufferTimes(int $bufferBefore, int $bufferAfter): void
    {
        if ($bufferBefore < 0) {
            throw new InvalidArgumentException('Buffer before cannot be negative');
        }

        if ($bufferAfter < 0) {
            throw new InvalidArgumentException('Buffer after cannot be negative');
        }

        if ($bufferBefore > 120) {
            throw new InvalidArgumentException('Buffer before cannot exceed 120 minutes');
        }

        if ($bufferAfter > 120) {
            throw new InvalidArgumentException('Buffer after cannot exceed 120 minutes');
        }
    }

    private static function validateSchedulingLimits(int $minNoticeHours, int $maxDaysAdvance, int $slotInterval): void
    {
        if ($minNoticeHours < 0) {
            throw new InvalidArgumentException('Minimum notice hours cannot be negative');
        }

        if ($maxDaysAdvance < 1) {
            throw new InvalidArgumentException('Maximum days advance must be at least 1');
        }

        if ($slotInterval < 5) {
            throw new InvalidArgumentException('Slot interval must be at least 5 minutes');
        }
    }

    // ========== Entity Implementation ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function equals(\App\Domain\Shared\Contracts\Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->id !== null && $this->id === $other->id;
    }

    // ========== Getters ==========

    public function schedulingPageId(): int
    {
        return $this->schedulingPageId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function duration(): MeetingDuration
    {
        return $this->duration;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function locationType(): LocationType
    {
        return $this->locationType;
    }

    public function locationDetails(): ?string
    {
        return $this->locationDetails;
    }

    public function color(): string
    {
        return $this->color;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @return array<mixed>
     */
    public function questions(): array
    {
        return $this->questions;
    }

    public function bufferBefore(): int
    {
        return $this->bufferBefore;
    }

    public function bufferAfter(): int
    {
        return $this->bufferAfter;
    }

    public function minNoticeHours(): int
    {
        return $this->minNoticeHours;
    }

    public function maxDaysAdvance(): int
    {
        return $this->maxDaysAdvance;
    }

    public function slotInterval(): int
    {
        return $this->slotInterval;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?Timestamp
    {
        return $this->updatedAt;
    }
}
