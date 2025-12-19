<?php

declare(strict_types=1);

namespace App\Domain\Modules\Entities;

use App\Domain\Modules\ValueObjects\BlockType;
use DateTimeImmutable;

final class Block
{
    private array $fields = [];

    public function __construct(
        private ?int $id,
        private int $moduleId,
        private string $name,
        private BlockType $type,
        private int $displayOrder,
        private array $settings,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function create(
        int $moduleId,
        string $name,
        BlockType $type = BlockType::SECTION,
        int $displayOrder = 0,
        array $settings = []
    ): self {
        return new self(
            id: null,
            moduleId: $moduleId,
            name: $name,
            type: $type,
            displayOrder: $displayOrder,
            settings: $settings,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function addField(Field $field): void
    {
        $this->fields[] = $field;
    }

    public function updateDetails(
        string $name,
        BlockType $type,
        array $settings
    ): void {
        $this->name = $name;
        $this->type = $type;
        $this->settings = $settings;
    }

    public function updateDisplayOrder(int $displayOrder): void
    {
        $this->displayOrder = $displayOrder;
    }

    // Getters
    public function id(): ?int
    {
        return $this->id;
    }

    public function moduleId(): int
    {
        return $this->moduleId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): BlockType
    {
        return $this->type;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
