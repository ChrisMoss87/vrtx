<?php

declare(strict_types=1);

namespace App\Domain\Reporting\DTOs;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Data Transfer Object for creating a new dashboard.
 */
final readonly class CreateDashboardDTO implements JsonSerializable
{
    /**
     * @param string $name Dashboard name
     * @param string|null $description Optional description
     * @param int|null $userId User ID who owns this dashboard
     * @param bool $isDefault Whether this is the default dashboard
     * @param bool $isPublic Whether this dashboard is public
     * @param array<mixed> $layout Layout configuration
     * @param array<mixed> $settings Dashboard settings
     * @param array<mixed> $filters Global dashboard filters
     * @param int $refreshInterval Auto-refresh interval in seconds
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?int $userId = null,
        public bool $isDefault = false,
        public bool $isPublic = false,
        public array $layout = [],
        public array $settings = [],
        public array $filters = [],
        public int $refreshInterval = 0,
    ) {
        $this->validate();
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? throw new InvalidArgumentException('Name is required'),
            description: $data['description'] ?? null,
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            isDefault: (bool) ($data['is_default'] ?? false),
            isPublic: (bool) ($data['is_public'] ?? false),
            layout: $data['layout'] ?? [],
            settings: $data['settings'] ?? [],
            filters: $data['filters'] ?? [],
            refreshInterval: (int) ($data['refresh_interval'] ?? 0),
        );
    }

    /**
     * Validate the DTO.
     */
    private function validate(): void
    {
        if (empty(trim($this->name))) {
            throw new InvalidArgumentException('Dashboard name cannot be empty');
        }

        if (strlen($this->name) > 255) {
            throw new InvalidArgumentException('Dashboard name cannot exceed 255 characters');
        }

        if ($this->refreshInterval < 0) {
            throw new InvalidArgumentException('Refresh interval cannot be negative');
        }
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'user_id' => $this->userId,
            'is_default' => $this->isDefault,
            'is_public' => $this->isPublic,
            'layout' => $this->layout,
            'settings' => $this->settings,
            'filters' => $this->filters,
            'refresh_interval' => $this->refreshInterval,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
