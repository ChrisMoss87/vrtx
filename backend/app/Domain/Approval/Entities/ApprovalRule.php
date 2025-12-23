<?php

declare(strict_types=1);

namespace App\Domain\Approval\Entities;

use App\Domain\Approval\ValueObjects\ApprovalType;
use App\Domain\Approval\ValueObjects\EntityType;
use App\Domain\Shared\Contracts\Entity;
use DateTimeImmutable;

final class ApprovalRule implements Entity
{
    private function __construct(
        private ?int $id,
        private string $name,
        private ?string $description,
        private EntityType $entityType,
        private ?int $moduleId,
        private array $conditions,
        private array $approverChain,
        private ApprovalType $approvalType,
        private bool $allowSelfApproval,
        private bool $requireComments,
        private ?int $slaHours,
        private array $escalationRules,
        private array $notificationSettings,
        private bool $isActive,
        private int $priority,
        private ?int $createdBy,
        private ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $name,
        EntityType $entityType,
        array $approverChain,
        ?string $description = null,
        ?int $moduleId = null,
        array $conditions = [],
        ApprovalType $approvalType = ApprovalType::SEQUENTIAL,
        bool $allowSelfApproval = false,
        bool $requireComments = false,
        ?int $slaHours = null,
        array $escalationRules = [],
        array $notificationSettings = [],
        int $priority = 0,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            name: $name,
            description: $description,
            entityType: $entityType,
            moduleId: $moduleId,
            conditions: $conditions,
            approverChain: $approverChain,
            approvalType: $approvalType,
            allowSelfApproval: $allowSelfApproval,
            requireComments: $requireComments,
            slaHours: $slaHours,
            escalationRules: $escalationRules,
            notificationSettings: $notificationSettings,
            isActive: true,
            priority: $priority,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable(),
            updatedAt: null,
        );
    }

    public static function reconstitute(
        int $id,
        string $name,
        ?string $description,
        EntityType $entityType,
        ?int $moduleId,
        array $conditions,
        array $approverChain,
        ApprovalType $approvalType,
        bool $allowSelfApproval,
        bool $requireComments,
        ?int $slaHours,
        array $escalationRules,
        array $notificationSettings,
        bool $isActive,
        int $priority,
        ?int $createdBy,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            description: $description,
            entityType: $entityType,
            moduleId: $moduleId,
            conditions: $conditions,
            approverChain: $approverChain,
            approvalType: $approvalType,
            allowSelfApproval: $allowSelfApproval,
            requireComments: $requireComments,
            slaHours: $slaHours,
            escalationRules: $escalationRules,
            notificationSettings: $notificationSettings,
            isActive: $isActive,
            priority: $priority,
            createdBy: $createdBy,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function matchesConditions(array $data): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            if (!$field || !isset($data[$field])) {
                continue;
            }

            $fieldValue = $data[$field];

            $matches = match ($operator) {
                '=' => $fieldValue == $value,
                '!=' => $fieldValue != $value,
                '>' => $fieldValue > $value,
                '>=' => $fieldValue >= $value,
                '<' => $fieldValue < $value,
                '<=' => $fieldValue <= $value,
                'in' => in_array($fieldValue, (array) $value),
                'not_in' => !in_array($fieldValue, (array) $value),
                default => false,
            };

            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getEntityType(): EntityType { return $this->entityType; }
    public function getModuleId(): ?int { return $this->moduleId; }
    public function getConditions(): array { return $this->conditions; }
    public function getApproverChain(): array { return $this->approverChain; }
    public function getApprovalType(): ApprovalType { return $this->approvalType; }
    public function allowsSelfApproval(): bool { return $this->allowSelfApproval; }
    public function requiresComments(): bool { return $this->requireComments; }
    public function getSlaHours(): ?int { return $this->slaHours; }
    public function getEscalationRules(): array { return $this->escalationRules; }
    public function getNotificationSettings(): array { return $this->notificationSettings; }
    public function isActive(): bool { return $this->isActive; }
    public function getPriority(): int { return $this->priority; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function getCreatedAt(): ?DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }

    public function equals(Entity $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->id !== null
            && $other->id !== null
            && $this->id === $other->id;
    }
}
