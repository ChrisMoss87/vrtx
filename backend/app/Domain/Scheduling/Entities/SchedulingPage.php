<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Entities;

use App\Domain\Shared\Contracts\AggregateRoot;
use App\Domain\Shared\Events\DomainEvent;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Shared\ValueObjects\Timestamp;
use App\Domain\Shared\ValueObjects\UserId;
use InvalidArgumentException;

/**
 * SchedulingPage aggregate root entity.
 *
 * Represents a public scheduling page that allows external parties
 * to book meetings with a user.
 */
final class SchedulingPage implements AggregateRoot
{
    use HasDomainEvents;

    /** @var array<MeetingType> */
    private array $meetingTypes = [];

    private function __construct(
        private ?int $id,
        private UserId $userId,
        private string $slug,
        private string $name,
        private ?string $description,
        private bool $isActive,
        private string $timezone,
        private array $branding,
        private ?Timestamp $createdAt,
        private ?Timestamp $updatedAt,
    ) {}

    /**
     * Create a new scheduling page.
     */
    public static function create(
        UserId $userId,
        string $slug,
        string $name,
        string $timezone,
        ?string $description = null,
        array $branding = [],
    ): self {
        self::validateSlug($slug);
        self::validateName($name);
        self::validateTimezone($timezone);

        return new self(
            id: null,
            userId: $userId,
            slug: $slug,
            name: $name,
            description: $description,
            isActive: true,
            timezone: $timezone,
            branding: $branding,
            createdAt: Timestamp::now(),
            updatedAt: null,
        );
    }

    /**
     * Reconstitute from persistence.
     */
    public static function reconstitute(
        int $id,
        UserId $userId,
        string $slug,
        string $name,
        ?string $description,
        bool $isActive,
        string $timezone,
        array $branding,
        ?Timestamp $createdAt,
        ?Timestamp $updatedAt,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            slug: $slug,
            name: $name,
            description: $description,
            isActive: $isActive,
            timezone: $timezone,
            branding: $branding,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    // ========== Behavior Methods ==========

    /**
     * Update the scheduling page details.
     */
    public function update(
        string $name,
        ?string $description,
        string $timezone,
        array $branding = [],
    ): void {
        self::validateName($name);
        self::validateTimezone($timezone);

        $this->name = $name;
        $this->description = $description;
        $this->timezone = $timezone;
        $this->branding = $branding;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Activate the scheduling page.
     */
    public function activate(): void
    {
        if ($this->isActive) {
            return;
        }

        $this->isActive = true;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Deactivate the scheduling page.
     */
    public function deactivate(): void
    {
        if (!$this->isActive) {
            return;
        }

        $this->isActive = false;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Change the slug.
     */
    public function changeSlug(string $newSlug): void
    {
        self::validateSlug($newSlug);

        if ($this->slug === $newSlug) {
            return;
        }

        $this->slug = $newSlug;
        $this->updatedAt = Timestamp::now();
    }

    /**
     * Add a meeting type to this page.
     */
    public function addMeetingType(MeetingType $meetingType): void
    {
        $this->meetingTypes[] = $meetingType;
    }

    /**
     * Set all meeting types at once.
     *
     * @param array<MeetingType> $meetingTypes
     */
    public function setMeetingTypes(array $meetingTypes): void
    {
        $this->meetingTypes = $meetingTypes;
    }

    /**
     * Get the public URL for this scheduling page.
     */
    public function publicUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/schedule/' . $this->slug;
    }

    // ========== Validation Methods ==========

    private static function validateSlug(string $slug): void
    {
        if (empty(trim($slug))) {
            throw new InvalidArgumentException('Slug cannot be empty');
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new InvalidArgumentException('Slug can only contain lowercase letters, numbers, and hyphens');
        }

        if (strlen($slug) > 100) {
            throw new InvalidArgumentException('Slug cannot exceed 100 characters');
        }
    }

    private static function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Name cannot be empty');
        }

        if (strlen($name) > 255) {
            throw new InvalidArgumentException('Name cannot exceed 255 characters');
        }
    }

    private static function validateTimezone(string $timezone): void
    {
        if (!in_array($timezone, \DateTimeZone::listIdentifiers())) {
            throw new InvalidArgumentException('Invalid timezone: ' . $timezone);
        }
    }

    // ========== AggregateRoot Implementation ==========

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

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    /**
     * @return array<string, mixed>
     */
    public function branding(): array
    {
        return $this->branding;
    }

    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?Timestamp
    {
        return $this->updatedAt;
    }

    /**
     * @return array<MeetingType>
     */
    public function meetingTypes(): array
    {
        return $this->meetingTypes;
    }
}
