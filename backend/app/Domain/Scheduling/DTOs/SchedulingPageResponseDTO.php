<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\DTOs;

use App\Domain\Scheduling\Entities\SchedulingPage;
use JsonSerializable;

/**
 * Data Transfer Object for scheduling page responses.
 */
final readonly class SchedulingPageResponseDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $slug,
        public string $name,
        public ?string $description,
        public bool $isActive,
        public string $timezone,
        public array $branding,
        public string $publicUrl,
        public array $meetingTypes,
        public string $createdAt,
        public ?string $updatedAt,
    ) {}

    public static function fromEntity(SchedulingPage $page, string $baseUrl = ''): self
    {
        return new self(
            id: $page->getId(),
            userId: $page->userId()->value(),
            slug: $page->slug(),
            name: $page->name(),
            description: $page->description(),
            isActive: $page->isActive(),
            timezone: $page->timezone(),
            branding: $page->branding(),
            publicUrl: $page->publicUrl($baseUrl),
            meetingTypes: array_map(
                fn($mt) => MeetingTypeResponseDTO::fromEntity($mt)->toArray(),
                $page->meetingTypes()
            ),
            createdAt: $page->createdAt()?->toDateTimeString() ?? '',
            updatedAt: $page->updatedAt()?->toDateTimeString(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'timezone' => $this->timezone,
            'branding' => $this->branding,
            'public_url' => $this->publicUrl,
            'meeting_types' => $this->meetingTypes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
