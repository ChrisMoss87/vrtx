<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Entities;

use App\Domain\Shared\ValueObjects\UserId;

/**
 * Blueprint aggregate root - represents a state machine for a module field.
 */
class Blueprint
{
    private ?int $id = null;
    private string $name;
    private int $moduleId;
    private int $fieldId;
    private ?string $description;
    private bool $isActive;
    private array $layoutData;
    /** @var BlueprintState[] */
    private array $states = [];
    /** @var BlueprintTransition[] */
    private array $transitions = [];
    /** @var BlueprintSla[] */
    private array $slas = [];
    private ?\DateTimeImmutable $createdAt = null;
    private ?\DateTimeImmutable $updatedAt = null;

    private function __construct(
        string $name,
        int $moduleId,
        int $fieldId,
        ?string $description = null,
        bool $isActive = true,
        array $layoutData = [],
    ) {
        $this->name = $name;
        $this->moduleId = $moduleId;
        $this->fieldId = $fieldId;
        $this->description = $description;
        $this->isActive = $isActive;
        $this->layoutData = $layoutData;
    }

    public static function create(
        string $name,
        int $moduleId,
        int $fieldId,
        ?string $description = null,
    ): self {
        return new self(
            name: $name,
            moduleId: $moduleId,
            fieldId: $fieldId,
            description: $description,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        int $moduleId,
        int $fieldId,
        ?string $description,
        bool $isActive,
        array $layoutData,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $blueprint = new self(
            name: $name,
            moduleId: $moduleId,
            fieldId: $fieldId,
            description: $description,
            isActive: $isActive,
            layoutData: $layoutData,
        );
        $blueprint->id = $id;
        $blueprint->createdAt = $createdAt;
        $blueprint->updatedAt = $updatedAt;

        return $blueprint;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getModuleId(): int
    {
        return $this->moduleId;
    }

    public function getFieldId(): int
    {
        return $this->fieldId;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getLayoutData(): array
    {
        return $this->layoutData;
    }

    /** @return BlueprintState[] */
    public function getStates(): array
    {
        return $this->states;
    }

    /** @return BlueprintTransition[] */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    /** @return BlueprintSla[] */
    public function getSlas(): array
    {
        return $this->slas;
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
        ?array $layoutData = null,
    ): void {
        $this->name = $name;
        $this->description = $description;
        if ($layoutData !== null) {
            $this->layoutData = $layoutData;
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

    public function setStates(array $states): void
    {
        $this->states = $states;
    }

    public function addState(BlueprintState $state): void
    {
        $this->states[] = $state;
    }

    public function setTransitions(array $transitions): void
    {
        $this->transitions = $transitions;
    }

    public function addTransition(BlueprintTransition $transition): void
    {
        $this->transitions[] = $transition;
    }

    public function setSlas(array $slas): void
    {
        $this->slas = $slas;
    }

    public function getInitialState(): ?BlueprintState
    {
        foreach ($this->states as $state) {
            if ($state->isInitial()) {
                return $state;
            }
        }
        return null;
    }

    /** @return BlueprintState[] */
    public function getTerminalStates(): array
    {
        return array_filter($this->states, fn(BlueprintState $state) => $state->isTerminal());
    }

    public function getStateById(int $stateId): ?BlueprintState
    {
        foreach ($this->states as $state) {
            if ($state->getId() === $stateId) {
                return $state;
            }
        }
        return null;
    }

    public function getStateByFieldValue(string $fieldValue): ?BlueprintState
    {
        foreach ($this->states as $state) {
            if ($state->getFieldOptionValue() === $fieldValue) {
                return $state;
            }
        }
        return null;
    }

    public function getTransitionById(int $transitionId): ?BlueprintTransition
    {
        foreach ($this->transitions as $transition) {
            if ($transition->getId() === $transitionId) {
                return $transition;
            }
        }
        return null;
    }

    /** @return BlueprintTransition[] */
    public function getTransitionsFromState(?int $stateId): array
    {
        return array_filter(
            $this->transitions,
            fn(BlueprintTransition $t) => $t->getFromStateId() === $stateId && $t->isActive()
        );
    }

    public function getSlaForState(int $stateId): ?BlueprintSla
    {
        foreach ($this->slas as $sla) {
            if ($sla->getStateId() === $stateId) {
                return $sla;
            }
        }
        return null;
    }

    /**
     * Validate the blueprint configuration.
     * @return array<string> List of validation errors
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->states)) {
            $errors[] = 'Blueprint must have at least one state';
        }

        $initialStates = array_filter($this->states, fn($s) => $s->isInitial());
        if (count($initialStates) === 0) {
            $errors[] = 'Blueprint must have exactly one initial state';
        } elseif (count($initialStates) > 1) {
            $errors[] = 'Blueprint cannot have multiple initial states';
        }

        $terminalStates = array_filter($this->states, fn($s) => $s->isTerminal());
        if (count($terminalStates) === 0) {
            $errors[] = 'Blueprint must have at least one terminal state';
        }

        return $errors;
    }
}
