<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\DTOs;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for creating a new scheduling page.
 */
final readonly class CreateSchedulingPageDTO implements JsonSerializable
{
    public function __construct(
        public int $userId,
        public string $slug,
        public string $name,
        public string $timezone,
        public ?string $description = null,
        public array $branding = [],
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: (int) ($data['user_id'] ?? throw new InvalidArgumentException('User ID is required')),
            slug: $data['slug'] ?? throw new InvalidArgumentException('Slug is required'),
            name: $data['name'] ?? throw new InvalidArgumentException('Name is required'),
            timezone: $data['timezone'] ?? throw new InvalidArgumentException('Timezone is required'),
            description: $data['description'] ?? null,
            branding: $data['branding'] ?? [],
        );
    }

    private function validate(): void
    {
        if (empty(trim($this->name))) {
            throw new InvalidArgumentException('Name cannot be empty');
        }

        if (empty(trim($this->slug))) {
            throw new InvalidArgumentException('Slug cannot be empty');
        }

        if (!in_array($this->timezone, \DateTimeZone::listIdentifiers())) {
            throw new InvalidArgumentException('Invalid timezone');
        }
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'slug' => $this->slug,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'description' => $this->description,
            'branding' => $this->branding,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
