<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Entities;

/**
 * Represents a state within a blueprint state machine.
 */
class BlueprintState
{
    private ?int $id = null;
    private int $blueprintId;
    private string $name;
    private string $fieldOptionValue;
    private ?string $color;
    private bool $isInitial;
    private bool $isTerminal;
    private int $positionX;
    private int $positionY;
    private array $metadata;
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    private function __construct(
        int $blueprintId,
        string $name,
        string $fieldOptionValue,
        ?string $color = null,
        bool $isInitial = false,
        bool $isTerminal = false,
        int $positionX = 0,
        int $positionY = 0,
        array $metadata = [],
    ) {
        $this->blueprintId = $blueprintId;
        $this->name = $name;
        $this->fieldOptionValue = $fieldOptionValue;
        $this->color = $color;
        $this->isInitial = $isInitial;
        $this->isTerminal = $isTerminal;
        $this->positionX = $positionX;
        $this->positionY = $positionY;
        $this->metadata = $metadata;
    }

    public static function create(
        int $blueprintId,
        string $name,
        string $fieldOptionValue,
        ?string $color = null,
        bool $isInitial = false,
        bool $isTerminal = false,
        int $positionX = 0,
        int $positionY = 0,
        array $metadata = [],
    ): self {
        return new self(
            blueprintId: $blueprintId,
            name: $name,
            fieldOptionValue: $fieldOptionValue,
            color: $color,
            isInitial: $isInitial,
            isTerminal: $isTerminal,
            positionX: $positionX,
            positionY: $positionY,
            metadata: $metadata,
        );
    }

    public static function reconstitute(
        int $id,
        int $blueprintId,
        string $name,
        string $fieldOptionValue,
        ?string $color,
        bool $isInitial,
        bool $isTerminal,
        int $positionX,
        int $positionY,
        array $metadata,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $state = new self(
            blueprintId: $blueprintId,
            name: $name,
            fieldOptionValue: $fieldOptionValue,
            color: $color,
            isInitial: $isInitial,
            isTerminal: $isTerminal,
            positionX: $positionX,
            positionY: $positionY,
            metadata: $metadata,
        );
        $state->id = $id;
        $state->createdAt = $createdAt;
        $state->updatedAt = $updatedAt;

        return $state;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlueprintId(): int
    {
        return $this->blueprintId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFieldOptionValue(): string
    {
        return $this->fieldOptionValue;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function isInitial(): bool
    {
        return $this->isInitial;
    }

    public function isTerminal(): bool
    {
        return $this->isTerminal;
    }

    public function getPositionX(): int
    {
        return $this->positionX;
    }

    public function getPositionY(): int
    {
        return $this->positionY;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Domain methods
    public function update(
        string $name,
        ?string $color = null,
        ?int $positionX = null,
        ?int $positionY = null,
        ?array $metadata = null,
    ): void {
        $this->name = $name;
        $this->color = $color;
        if ($positionX !== null) {
            $this->positionX = $positionX;
        }
        if ($positionY !== null) {
            $this->positionY = $positionY;
        }
        if ($metadata !== null) {
            $this->metadata = $metadata;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setAsInitial(): void
    {
        $this->isInitial = true;
        $this->isTerminal = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setAsTerminal(): void
    {
        $this->isTerminal = true;
        $this->isInitial = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setAsIntermediate(): void
    {
        $this->isInitial = false;
        $this->isTerminal = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updatePosition(int $x, int $y): void
    {
        $this->positionX = $x;
        $this->positionY = $y;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
