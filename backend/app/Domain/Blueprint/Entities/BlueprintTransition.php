<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Entities;

/**
 * Represents a transition between states in a blueprint.
 */
class BlueprintTransition
{
    private ?int $id = null;
    private int $blueprintId;
    private ?int $fromStateId;
    private int $toStateId;
    private string $name;
    private ?string $description;
    private ?string $buttonLabel;
    private int $displayOrder;
    private bool $isActive;
    /** @var array Conditions configuration */
    private array $conditions = [];
    /** @var array Requirements configuration */
    private array $requirements = [];
    /** @var array Actions configuration */
    private array $actions = [];
    /** @var array|null Approval configuration */
    private ?array $approvalConfig = null;
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    private function __construct(
        int $blueprintId,
        ?int $fromStateId,
        int $toStateId,
        string $name,
        ?string $description = null,
        ?string $buttonLabel = null,
        int $displayOrder = 0,
        bool $isActive = true,
    ) {
        $this->blueprintId = $blueprintId;
        $this->fromStateId = $fromStateId;
        $this->toStateId = $toStateId;
        $this->name = $name;
        $this->description = $description;
        $this->buttonLabel = $buttonLabel;
        $this->displayOrder = $displayOrder;
        $this->isActive = $isActive;
    }

    public static function create(
        int $blueprintId,
        ?int $fromStateId,
        int $toStateId,
        string $name,
        ?string $description = null,
        ?string $buttonLabel = null,
        int $displayOrder = 0,
    ): self {
        return new self(
            blueprintId: $blueprintId,
            fromStateId: $fromStateId,
            toStateId: $toStateId,
            name: $name,
            description: $description,
            buttonLabel: $buttonLabel,
            displayOrder: $displayOrder,
        );
    }

    public static function reconstitute(
        int $id,
        int $blueprintId,
        ?int $fromStateId,
        int $toStateId,
        string $name,
        ?string $description,
        ?string $buttonLabel,
        int $displayOrder,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $transition = new self(
            blueprintId: $blueprintId,
            fromStateId: $fromStateId,
            toStateId: $toStateId,
            name: $name,
            description: $description,
            buttonLabel: $buttonLabel,
            displayOrder: $displayOrder,
            isActive: $isActive,
        );
        $transition->id = $id;
        $transition->createdAt = $createdAt;
        $transition->updatedAt = $updatedAt;

        return $transition;
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

    public function getFromStateId(): ?int
    {
        return $this->fromStateId;
    }

    public function getToStateId(): int
    {
        return $this->toStateId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getButtonLabel(): string
    {
        return $this->buttonLabel ?? $this->name;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function getApprovalConfig(): ?array
    {
        return $this->approvalConfig;
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
        ?string $description = null,
        ?string $buttonLabel = null,
        ?int $displayOrder = null,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->buttonLabel = $buttonLabel;
        if ($displayOrder !== null) {
            $this->displayOrder = $displayOrder;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setConditions(array $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function setRequirements(array $requirements): void
    {
        $this->requirements = $requirements;
    }

    public function setActions(array $actions): void
    {
        $this->actions = $actions;
    }

    public function setApprovalConfig(?array $approvalConfig): void
    {
        $this->approvalConfig = $approvalConfig;
    }

    public function requiresApproval(): bool
    {
        return $this->approvalConfig !== null && !empty($this->approvalConfig);
    }

    public function hasConditions(): bool
    {
        return !empty($this->conditions);
    }

    public function hasRequirements(): bool
    {
        return !empty($this->requirements);
    }

    public function hasActions(): bool
    {
        return !empty($this->actions);
    }

    /**
     * Check if this is an initial transition (from null state).
     */
    public function isInitialTransition(): bool
    {
        return $this->fromStateId === null;
    }
}
